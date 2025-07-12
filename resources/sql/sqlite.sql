-- #! sqlite
-- #{ customgui
-- #    { init
CREATE TABLE IF NOT EXISTS custom_gui (
    name TEXT PRIMARY KEY NOT NULL,
    data BLOB NOT NULL
);
-- #    }
-- #    { load
SELECT name, HEX(data) AS data FROM custom_gui;
-- #    }
-- #    { add
-- #        :name string
-- #        :data string
INSERT OR REPLACE INTO custom_gui (name, data) VALUES (:name, X:data);
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