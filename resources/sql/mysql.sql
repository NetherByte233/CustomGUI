-- #! mysql
-- #{ customgui
-- #    { init
CREATE TABLE IF NOT EXISTS custom_gui (
    name TEXT PRIMARY KEY NOT NULL,
    data BLOB NOT NULL
);
-- #    }
-- #    { load
SELECT * FROM custom_gui;
-- #    }
-- #    { add
-- #        :name string
-- #        :data string
INSERT INTO custom_gui (name, data) VALUES (:name, :data)
ON DUPLICATE KEY UPDATE data = VALUES(data);
-- #    }
-- #    { update
-- #        :name string
-- #        :data string
UPDATE custom_gui SET data = :data WHERE name = :name;
-- #    }
-- #    { delete
-- #        :name string
DELETE FROM custom_gui WHERE name = :name;
-- #    }
-- #}
