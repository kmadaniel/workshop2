use distribution;

CREATE TABLE resource (
    resource_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50),
    quantity_available INT NOT NULL DEFAULT 0,
    unit VARCHAR(20)
);

CREATE TABLE victim (
    victim_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    age INT,
    address TEXT,
    disaster_id INT   -- optional (ikut ERD asal)
);

CREATE TABLE IF NOT EXISTS needs (
    need_id SERIAL PRIMARY KEY,
    victim_id BIGINT UNSIGNED NOT NULL,
    resource_id BIGINT UNSIGNED NOT NULL,
    quantity_needed INT NOT NULL,
    priority VARCHAR(20),
    status VARCHAR(20) DEFAULT 'Pending',
    distribution_id INT NULL
);

-- Drop existing foreign key if it exists
ALTER TABLE needs DROP FOREIGN KEY needs_ibfk_3;

-- Add the correct foreign key
ALTER TABLE needs ADD FOREIGN KEY (distribution_id) REFERENCES distribution(distribution_id);

CREATE TABLE distribution (
    distribution_id SERIAL PRIMARY KEY,
    date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'Pending',
    quantity_sent INT NOT NULL,
    comments TEXT
);

