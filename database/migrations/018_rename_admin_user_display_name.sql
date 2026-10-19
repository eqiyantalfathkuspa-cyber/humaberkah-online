UPDATE users AS u
INNER JOIN roles AS r ON r.id = u.role_id
SET u.full_name = 'Admin'
WHERE r.code = 'ADMIN' AND u.full_name = 'Admin Pokja 6';
