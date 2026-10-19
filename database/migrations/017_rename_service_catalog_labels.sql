UPDATE service_categories
SET name = CASE code
  WHEN 'TU' THEN 'TATA USAHA'
  WHEN 'KUR' THEN 'KURIKULUM'
  WHEN 'KES' THEN 'KESISWAAN'
  WHEN 'SAR' THEN 'SARANA PRASARANA'
  WHEN 'HUM' THEN 'HUMAS'
  ELSE name
END
WHERE code IN ('TU', 'KUR', 'KES', 'SAR', 'HUM');

UPDATE services
SET name = 'Srikandi'
WHERE code = 'TU-01' AND name <> 'Srikandi';
