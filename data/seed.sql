-- Sample data for stockflow development and manual API testing

INSERT INTO products (sku, name) VALUES
    ('WIDGET-A', 'Widget A'),
    ('WIDGET-B', 'Widget B'),
    ('GADGET-C', 'Gadget C');

INSERT INTO warehouses (code, name) VALUES
    ('WH-EU', 'Europe Central'),
    ('WH-US', 'United States East'),
    ('WH-ASIA', 'Asia Pacific');

INSERT INTO warehouse_stocks (warehouse_id, product_id, quantity, reserved_quantity) VALUES
    (1, 1, 10, 0),
    (1, 2, 5, 0),
    (2, 1, 8, 0),
    (2, 3, 12, 0),
    (3, 2, 20, 0),
    (3, 3, 4, 0);
