<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class SqlStatementParser
{
    private const UNSUPPORTED_CREATE_TYPES = ['PROCEDURE', 'FUNCTION', 'EVENT'];
    private const SUPPORTED_CREATE_TYPES = [
        'DATABASE',
        'INDEX',
        'ROLE',
        'SCHEMA',
        'TABLE',
        'TEMPORARY',
        'TRIGGER',
        'UNIQUE',
        'USER',
        'VIEW',
    ];

    public static function parse(string $sql, bool $noBackslashEscapes = false, bool $ansiQuotes = false): array
    {
        $tokens = self::tokenize($sql, $noBackslashEscapes, $ansiQuotes);
        $statements = [];
        $buffer = '';
        $statementWords = [];
        $createStatement = false;
        $createTypeSeen = false;
        $trigger = false;
        $blocks = [];
        $skipStructuralWordIndex = null;

        foreach ($tokens as $index => $token) {
            if ($token['kind'] === 'space') {
                $buffer .= $token['text'];
                continue;
            }

            if ($token['kind'] === 'semicolon') {
                if ($trigger && $blocks !== []) {
                    $buffer .= $token['text'];
                    continue;
                }

                self::appendStatement($statements, $buffer);
                $buffer = '';
                $statementWords = [];
                $createStatement = false;
                $createTypeSeen = false;
                $trigger = false;
                $blocks = [];
                $skipStructuralWordIndex = null;
                continue;
            }

            if ($token['kind'] === 'word') {
                $word = strtoupper($token['text']);
                if ($statementWords === [] && $word === 'DELIMITER') {
                    throw new RuntimeException('Perintah DELIMITER tidak didukung dalam file migrasi.');
                }

                if ($createStatement && !$createTypeSeen) {
                    if (in_array($word, self::UNSUPPORTED_CREATE_TYPES, true)) {
                        throw new RuntimeException('CREATE ' . $word . ' tidak didukung oleh parser migrasi.');
                    }
                    if (in_array($word, self::SUPPORTED_CREATE_TYPES, true)) {
                        $createTypeSeen = true;
                    }
                }

                if ($word === 'CREATE' && $statementWords === []) {
                    $createStatement = true;
                } elseif ($word === 'TRIGGER' && $createStatement) {
                    $trigger = true;
                    $createTypeSeen = true;
                }

                $statementWords[] = $word;
                if ($trigger && $skipStructuralWordIndex !== $index) {
                    if ($word === 'BEGIN') {
                        $blocks[] = 'BEGIN';
                    } elseif ($word === 'CASE') {
                        $blocks[] = 'CASE';
                    } elseif ($word === 'IF' && self::isControlIf($tokens, $index)) {
                        $blocks[] = 'IF';
                    } elseif ($word === 'END') {
                        [$suffixIndex, $suffix] = self::nextWord($tokens, $index);
                        if (in_array($suffix, ['IF', 'CASE'], true)) {
                            if (array_pop($blocks) !== $suffix) {
                                throw new RuntimeException('Blok trigger memiliki END ' . $suffix . ' yang tidak cocok.');
                            }
                            $skipStructuralWordIndex = $suffixIndex;
                        } elseif (in_array($suffix, ['LOOP', 'REPEAT', 'WHILE'], true)) {
                            throw new RuntimeException('Blok trigger ' . $suffix . ' tidak didukung oleh parser migrasi.');
                        } elseif ($blocks !== [] && in_array(end($blocks), ['BEGIN', 'CASE'], true)) {
                            array_pop($blocks);
                        } else {
                            throw new RuntimeException('Blok trigger memiliki END yang tidak cocok.');
                        }
                    }
                }

                if ($skipStructuralWordIndex === $index) {
                    $skipStructuralWordIndex = null;
                }
            }

            $buffer .= $token['text'];
        }

        if ($blocks !== []) {
            throw new RuntimeException('Blok trigger tidak lengkap: ' . implode(', ', $blocks) . '.');
        }

        self::appendStatement($statements, $buffer);
        return $statements;
    }

    private static function tokenize(string $sql, bool $noBackslashEscapes, bool $ansiQuotes): array
    {
        $tokens = [];
        $length = strlen($sql);

        for ($index = 0; $index < $length;) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if (ctype_space($char)) {
                $start = $index++;
                while ($index < $length && ctype_space($sql[$index])) {
                    $index++;
                }
                $tokens[] = ['kind' => 'space', 'text' => substr($sql, $start, $index - $start)];
                continue;
            }

            if ($char === '\'' || $char === '"' || $char === '`') {
                $quote = $char;
                $start = $index++;
                $closed = false;
                while ($index < $length) {
                    $backslashEscapesQuote = $quote === '`'
                        || (!$noBackslashEscapes && !($quote === '"' && $ansiQuotes));
                    if ($sql[$index] === '\\' && $index + 1 < $length && $backslashEscapesQuote) {
                        $index += 2;
                        continue;
                    }
                    if ($sql[$index] === $quote) {
                        if ($index + 1 < $length && $sql[$index + 1] === $quote) {
                            $index += 2;
                            continue;
                        }
                        $index++;
                        $closed = true;
                        break;
                    }
                    $index++;
                }
                if (!$closed) {
                    throw new RuntimeException('String atau identifier SQL tidak ditutup.');
                }
                $tokens[] = ['kind' => 'quoted', 'text' => substr($sql, $start, $index - $start)];
                continue;
            }

            if ($char === '-' && $next === '-' && ($index + 2 === $length || ctype_space($sql[$index + 2]))) {
                $index += 2;
                while ($index < $length && $sql[$index] !== "\n" && $sql[$index] !== "\r") {
                    $index++;
                }
                $tokens[] = ['kind' => 'space', 'text' => ' '];
                continue;
            }

            if ($char === '#') {
                while ($index < $length && $sql[$index] !== "\n" && $sql[$index] !== "\r") {
                    $index++;
                }
                $tokens[] = ['kind' => 'space', 'text' => ' '];
                continue;
            }

            if ($char === '/' && $next === '*') {
                $commentEnd = strpos($sql, '*/', $index + 2);
                if ($commentEnd === false) {
                    throw new RuntimeException('Komentar SQL tidak ditutup.');
                }
                $comment = substr($sql, $index, $commentEnd + 2 - $index);
                if (str_starts_with($comment, '/*!')) {
                    throw new RuntimeException('Komentar executable MySQL /*!...*/ tidak didukung oleh parser migrasi.');
                }
                $tokens[] = [
                    'kind' => str_starts_with($comment, '/*+') ? 'opaque' : 'space',
                    'text' => str_starts_with($comment, '/*+') ? $comment : ' ',
                ];
                $index = $commentEnd + 2;
                continue;
            }

            if ($char === ';') {
                $tokens[] = ['kind' => 'semicolon', 'text' => ';'];
                $index++;
                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $start = $index++;
                while ($index < $length && (ctype_alnum($sql[$index]) || $sql[$index] === '_')) {
                    $index++;
                }
                $tokens[] = ['kind' => 'word', 'text' => substr($sql, $start, $index - $start)];
                continue;
            }

            $tokens[] = ['kind' => 'symbol', 'text' => $char];
            $index++;
        }

        return $tokens;
    }

    private static function isControlIf(array $tokens, int $ifIndex): bool
    {
        $parenthesisDepth = 0;
        for ($index = $ifIndex + 1, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token['kind'] === 'semicolon') {
                return false;
            }
            if ($token['kind'] === 'symbol') {
                if ($token['text'] === '(') {
                    $parenthesisDepth++;
                } elseif ($token['text'] === ')') {
                    $parenthesisDepth--;
                }
                continue;
            }
            if ($token['kind'] === 'word' && $parenthesisDepth === 0) {
                $word = strtoupper($token['text']);
                if ($word === 'THEN') {
                    return true;
                }
                if (in_array($word, ['ELSE', 'ELSEIF', 'END'], true)) {
                    return false;
                }
            }
        }

        return false;
    }

    private static function nextWord(array $tokens, int $fromIndex): array
    {
        for ($index = $fromIndex + 1, $count = count($tokens); $index < $count; $index++) {
            if ($tokens[$index]['kind'] === 'word') {
                return [$index, strtoupper($tokens[$index]['text'])];
            }
            if (!in_array($tokens[$index]['kind'], ['space', 'opaque'], true)) {
                break;
            }
        }

        return [null, null];
    }

    private static function appendStatement(array &$statements, string $buffer): void
    {
        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }
    }
}
