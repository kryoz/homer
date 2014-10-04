<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

$db = new PDO(DB_SCHEME.'dbname='.HOMER_DB.';host=localhost', HOMER_DBUSER, HOMER_DBPASS, [
    1002 => "SET NAMES utf8",
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);


try {

    $db->exec("DROP TABLE IF EXISTS queue");

    $db->exec("
    CREATE TABLE IF NOT EXISTS queue (
        id SERIAL PRIMARY KEY,
        url VARCHAR(255) UNIQUE,
        deep SMALLINT
    ) WITH (OIDS=FALSE)");

    $db->exec("DROP TABLE IF EXISTS indexes");

    $db->exec("
    CREATE TABLE indexes (
        url VARCHAR(255) PRIMARY KEY,
        title VARCHAR(255),
        body TEXT
    ) WITH (OIDS=FALSE)");

    $sql = <<<SQL
-- Add the new tsvector column
ALTER TABLE indexes ADD COLUMN tsv tsvector;

-- Create a function that will generate a tsvector from text data found in both the
-- title and body columns, but give a higher relevancy rating 'A' to the title data
CREATE FUNCTION indexes_generate_tsvector() RETURNS trigger AS $$
  begin
    new.tsv :=
      setweight(to_tsvector(coalesce(new.title,'')), 'A') ||
      setweight(to_tsvector(coalesce(new.body,'')), 'B');
    return new;
  end
$$ LANGUAGE plpgsql;

-- When articles row data is inserted or updated, execute the function
-- that generates the tsvector data for that row
CREATE TRIGGER tsvector_indexes_upsert_trigger BEFORE INSERT OR UPDATE
  ON indexes
  FOR EACH ROW EXECUTE PROCEDURE indexes_generate_tsvector();

-- When the migration is run, create tsvector data for all the existing records
UPDATE indexes SET tsv =
  setweight(to_tsvector(coalesce(title,'')), 'A') ||
  setweight(to_tsvector(coalesce(body,'')), 'B');

-- Create an index for the tsv column that is specialised for tsvector data
CREATE INDEX indexes_tsv_idx ON indexes USING gin(tsv);
SQL;

    $db->exec($sql);
    echo "Migrated successfully.\n";

} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}

