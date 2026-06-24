-- Sample data for stockflow development and manual API testing
-- Safe to run multiple times (idempotent).

INSERT INTO products (sku, name) VALUES
    ('WIDGET-A', 'Widget A'),
    ('WIDGET-B', 'Widget B'),
    ('GADGET-C', 'Gadget C')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO warehouses (code, name) VALUES
    ('WH-EU', 'Europe Central'),
    ('WH-US', 'United States East'),
    ('WH-ASIA', 'Asia Pacific')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO warehouse_stocks (warehouse_id, product_id, quantity, reserved_quantity)
SELECT w.id, p.id, v.quantity, v.reserved_quantity
FROM (
    SELECT 'WH-EU' AS warehouse_code, 'WIDGET-A' AS product_sku, 10 AS quantity, 0 AS reserved_quantity
    UNION ALL SELECT 'WH-EU', 'WIDGET-B', 5, 0
    UNION ALL SELECT 'WH-US', 'WIDGET-A', 8, 0
    UNION ALL SELECT 'WH-US', 'GADGET-C', 12, 0
    UNION ALL SELECT 'WH-ASIA', 'WIDGET-B', 20, 0
    UNION ALL SELECT 'WH-ASIA', 'GADGET-C', 4, 0
) AS v
INNER JOIN warehouses w ON w.code = v.warehouse_code
INNER JOIN products p ON p.sku = v.product_sku
ON DUPLICATE KEY UPDATE
    quantity = VALUES(quantity),
    reserved_quantity = VALUES(reserved_quantity);
