insert into system_colors (id)
values (1);

insert into cat_income_types (name,
                              description,
                              is_periodic,
                              frequency_days,
                              tentative_payment_month,
                              tentative_payment_day,
                              active)
VALUES ('Nomina',
        'Ingreso por concepto de nómina',
        true,
        15,
        null,
        15,
        true),
       ('DIA SERVIDOR PUBLICO',
        'Día del Servidor Público (conmemoración)',
        false,
        null,
        6,
        30,
        true),
       ('DIA DEL PADRE',
        'Día del Padre (tercer domingo de junio) — 2026: 21/jun',
        false,
        null,
        6,
        21,
        true),
       ('DIA DE LA MADRE',
        'Día de la Madre — 2026: 10/may',
        false,
        null,
        5,
        10,
        true),
       ('DIA DEL MAESTRO',
        'Día del Maestro — 2026: 15/may',
        false,
        null,
        5,
        15,
        true),
       ('Prima Vacacional 12 Dias, 1ER. Periodo',
        'Prima vacacional — primer periodo (fecha recomendada)',
        false,
        null,
        6,
        1,
        true),
       ('RETROACTIVO',
        'Pago retroactivo',
        false,
        null,
        6,
        10,
        true),
       ('Aguinaldo 40 Dias',
        'Aguinaldo (pago anual) — fecha límite legal en México: 20/dic',
        false,
        null,
        12,
        20,
        true),
       ('Prima Vacacional 12 Dias, 2do. Periodo',
        'Prima vacacional — segundo periodo (fecha recomendada)',
        false,
        null,
        12,
        1,
        true),
       ('Bono Navideno',
        'Bono navideño anual (fecha recomendada)',
        false,
        null,
        12,
        15,
        true),
       ('Ajuste al Calendario 5 Dias',
        'Ajuste al calendario (5 días) — aplicado fin de año',
        false,
        null,
        12,
        31,
        true),
       ('9 DIAS Otras Prestaciones de Fin de Ano',
        'Otras prestaciones de fin de año (9 días) — recomendada en dic',
        false,
        null,
        12,
        20,
        true),
       ('5 DIAS Complemento al Aguinaldo',
        'Complemento al aguinaldo (5 días) — recomendada en dic',
        false,
        null,
        12,
        20,
        true);


INSERT INTO transaction_categories (name, type, description, contribution_category)
VALUES ('Cuota Ordinaria', 'income', 'Descuento quincenal aplicado por el patrón a agremiados activos.', 'ordinary'),
       ('Cuota Extraordinaria', 'income', 'Aportaciones adicionales autorizadas por la asamblea o comité.', 'extraordinary'),
       ('Aportación Personal de Confianza', 'income', 'Transferencia voluntaria de personal de confianza.', 'trust_staff'),
       ('Cuota Personal Jubilado', 'income', 'Aportación voluntaria de personal jubilado.', 'retired'),
       ('Cuota de Permiso Sindical', 'income', 'Aportación de agremiados con permiso sindical.', 'union_leave'),
       ('Otros ingresos', 'income', 'Ingresos no clasificados en cuotas estándar.', 'other'),
       ('Servicios jurídicos', 'expense', 'Pago de asesoría y representación legal del sindicato.', NULL),
       ('Aportación SNES', 'expense', 'Cuota o aportación institucional al SNES.', NULL),
       ('Mantenimiento de oficina', 'expense', 'Limpieza, reparaciones y mantenimiento general de oficina.', NULL),
       ('Apoyos de solidaridad', 'expense', 'Apoyos por enfermedad, fallecimiento u otras contingencias.', NULL),
       ('Eventos sindicales', 'expense', 'Gastos de convivencias y actividades institucionales.', NULL),
       ('Otros gastos', 'expense', 'Egresos no clasificados en categorías predefinidas.', NULL);


-- Tipos de solicitud
INSERT INTO request_types (name, description, active)
VALUES ('Lentes', 'Solicitud de apoyo para adquisición de lentes oftálmicos.', TRUE),
       ('Laptop', 'Solicitud de apoyo para adquisición de equipo de cómputo portátil.', TRUE);

-- 1. Nómina
UPDATE cat_income_types 
SET name = 'Nómina', 
    description = 'Ingreso por concepto de nómina' 
WHERE name = 'Nomina';

-- 2. Día del Servidor Público
UPDATE cat_income_types 
SET name = 'Día del Servidor Público', 
    description = 'Día del Servidor Público (conmemoración)' 
WHERE name = 'DIA SERVIDOR PUBLICO';

-- 3. Día del Padre
UPDATE cat_income_types 
SET name = 'Día del Padre', 
    description = 'Día del Padre (tercer domingo de junio) — 2026: 21/jun' 
WHERE name = 'DIA DEL PADRE';

-- 4. Día de la Madre
UPDATE cat_income_types 
SET name = 'Día de la Madre', 
    description = 'Día de la Madre — 2026: 10/may' 
WHERE name = 'DIA DE LA MADRE';

-- 5. Día del Maestro
UPDATE cat_income_types 
SET name = 'Día del Maestro', 
    description = 'Día del Maestro — 2026: 15/may' 
WHERE name = 'DIA DEL MAESTRO';

-- 6. Prima Vacacional (1er Periodo)
UPDATE cat_income_types 
SET name = 'Prima Vacacional 12 Días, 1er. Periodo', 
    description = 'Prima vacacional — primer periodo (fecha recomendada)' 
WHERE name = 'Prima Vacacional 12 Dias, 1ER. Periodo';

-- 7. Retroactivo
UPDATE cat_income_types 
SET name = 'Retroactivo', 
    description = 'Pago retroactivo' 
WHERE name = 'RETROACTIVO';

-- 8. Aguinaldo
UPDATE cat_income_types 
SET name = 'Aguinaldo 40 Días', 
    description = 'Aguinaldo (pago anual) — fecha límite legal en México: 20/dic' 
WHERE name = 'Aguinaldo 40 Dias';

-- 9. Prima Vacacional (2do Periodo)
UPDATE cat_income_types 
SET name = 'Prima Vacacional 12 Días, 2do. Periodo', 
    description = 'Prima vacacional — segundo periodo (fecha recomendada)' 
WHERE name = 'Prima Vacacional 12 Dias, 2do. Periodo';

-- 10. Bono Navideño
UPDATE cat_income_types 
SET name = 'Bono Navideño', 
    description = 'Bono navideño anual (fecha recomendada)' 
WHERE name = 'Bono Navideno';

-- 11. Ajuste al Calendario
UPDATE cat_income_types 
SET name = 'Ajuste al Calendario 5 Días', 
    description = 'Ajuste al calendario (5 días) — aplicado fin de año' 
WHERE name = 'Ajuste al Calendario 5 Dias';

-- 12. Otras Prestaciones de Fin de Año
UPDATE cat_income_types 
SET name = '9 Días Otras Prestaciones de Fin de Año', 
    description = 'Otras prestaciones de fin de año (9 días) — recomendada en dic' 
WHERE name = '9 DIAS Otras Prestaciones de Fin de Ano';

-- 13. Complemento al Aguinaldo
UPDATE cat_income_types 
SET name = '5 Días Complemento al Aguinaldo', 
    description = 'Complemento al aguinaldo (5 días) — recomendada en dic' 
WHERE name = '5 DIAS Complemento al Aguinaldo';
