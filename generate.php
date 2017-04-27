#!/usr/bin/php
<?php
/**
 * BSD 3 Clause License
 * Copyright (c) 2017, John Dunlap<john.david.dunlap@gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *    - Redistributions of source code must retain the above copyright notice, this
 *      list of conditions and the following disclaimer.
 *    - Redistributions in binary form must reproduce the above copyright notice,
 *      this list of conditions and the following disclaimer in the documentation
 *      and/or other materials provided with the distribution.
 *    - Neither the name of the copyright holder nor the names of its contributors may
 *      be used to endorse or promote products derived from this software without 
 *      specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY 
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES 
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT 
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT 
 * OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR 
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 * EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

if (!array_key_exists(1, $argv)) {
	print "Syntax: generate.sh <TABLE_NAME>\n";
	exit(1);
}

$tableName = $argv[1];

spl_autoload_register(function($className) {
    $base = realpath(dirname(__FILE__) );

    $filename = $base . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';

    if( file_exists($filename)) {
        include $filename;
    }
},false,true);

use lib\SQL\DataSource;
use lib\SQL\PostgreSQL\PostgreSQLConnectionFactory;

$dataSource = new DataSource(
	getenv("PGUSER"),
	getenv("PGPASSWORD"),
	getenv("PGDATABASE"),
	getenv("PGHOST"),
	getenv("PGPORT")
);

$connectionFactory = new PostgreSQLConnectionFactory($dataSource);
$connection = $connectionFactory->getConnection();

$exists = $connection->fetchField("
	select
		count(*)
	FROM information_schema.columns
	WHERE table_schema = 'public'
		AND table_name = ?
	",
	array($tableName)
);

if (!$exists) {
        print "ERROR: Table $tableName does not exist\n";
        print "Syntax: generate.sh <TABLE_NAME>\n";
	exit(2);
}

$columns = $connection->fetchAll("
	select
		table_name,
		column_name,
		case when data_type = 'character varying' then 'text' else data_type end as data_type
	FROM information_schema.columns
	WHERE table_schema = 'public'
		AND table_name = ?
", array($tableName));

?>
CREATE OR REPLACE FUNCTION public.is_different(boolean, boolean)
	RETURNS boolean
LANGUAGE plpgsql
AS $function$
DECLARE
	lhs ALIAS FOR $1;
	rhs ALIAS FOR $2;
BEGIN
	if ((lhs IS NULL and rhs IS NOT NULL) or (lhs IS NOT NULL and rhs IS NULL) or (lhs != rhs)) THEN
		return true;
	END IF;
	
	return false;
END;
$function$;

CREATE OR REPLACE FUNCTION public.is_different(bigint, bigint)
	RETURNS boolean
LANGUAGE plpgsql
AS $function$
DECLARE
	lhs ALIAS FOR $1;
	rhs ALIAS FOR $2;
BEGIN
	if ((lhs IS NULL and rhs IS NOT NULL) or (lhs IS NOT NULL and rhs IS NULL) or (lhs != rhs)) THEN
		return true;
	END IF;
	
	return false;
END;
$function$;

CREATE OR REPLACE FUNCTION public.is_different(integer, integer)
	RETURNS boolean
LANGUAGE plpgsql
AS $function$
DECLARE
	lhs ALIAS FOR $1;
	rhs ALIAS FOR $2;
BEGIN
	if ((lhs IS NULL and rhs IS NOT NULL) or (lhs IS NOT NULL and rhs IS NULL) or (lhs != rhs)) THEN
		return true;
	END IF;
	
	return false;
END;
$function$;

CREATE OR REPLACE FUNCTION public.is_different(character varying, character varying)
	RETURNS boolean
LANGUAGE plpgsql
AS $function$
DECLARE
	lhs ALIAS FOR $1;
	rhs ALIAS FOR $2;
BEGIN
	if ((lhs IS NULL and rhs IS NOT NULL) or (lhs IS NOT NULL and rhs IS NULL) or (lhs != rhs)) THEN
		return true;
	END IF;
	
	return false;
END;
$function$;

CREATE OR REPLACE FUNCTION public.is_different(timestamp without time zone, timestamp without time zone)
	RETURNS boolean
LANGUAGE plpgsql
AS $function$
DECLARE
	lhs ALIAS FOR $1;
	rhs ALIAS FOR $2;
BEGIN
	if ((lhs IS NULL and rhs IS NOT NULL) or (lhs IS NOT NULL and rhs IS NULL) or (lhs != rhs)) THEN
		return true;
	END IF;
	
	return false;
END;
$function$;

CREATE OR REPLACE FUNCTION public.is_different(timestamp with time zone, timestamp with time zone)
	RETURNS boolean
LANGUAGE plpgsql
AS $function$
DECLARE
	lhs ALIAS FOR $1;
	rhs ALIAS FOR $2;
BEGIN
	if ((lhs IS NULL and rhs IS NOT NULL) or (lhs IS NOT NULL and rhs IS NULL) or (lhs != rhs)) THEN
		return true;
	END IF;
	
	return false;
END;
$function$;

CREATE OR REPLACE FUNCTION public.is_different(numeric, numeric)
	RETURNS boolean
LANGUAGE plpgsql
AS $function$
DECLARE
	lhs ALIAS FOR $1;
	rhs ALIAS FOR $2;
BEGIN
	if ((lhs IS NULL and rhs IS NOT NULL) or (lhs IS NOT NULL and rhs IS NULL) or (lhs != rhs)) THEN
		return true;
	END IF;
	
	return false;
END;
$function$;
create table if not exists aud_<?=$tableName?> (
	id bigserial unique not null
	,changed_by_username text not null
	,changed_at_timestamp timestamp not null
<?php foreach ($columns as $column) {
	print "\t,old_" . $column['column_name'] . " " . $column['data_type'] . "\n";
	print "\t,new_" . $column['column_name'] . " " . $column['data_type'] . "\n";
} ?>
);

drop trigger if exists <?=$tableName?>_aud_insert_trigger ON <?=$tableName?>;
drop trigger if exists <?=$tableName?>_aud_update_trigger ON <?=$tableName?>;
drop trigger if exists <?=$tableName?>_aud_delete_trigger ON <?=$tableName?>;
drop function if exists audit_<?=$tableName?>_changes();
CREATE OR REPLACE FUNCTION public.audit_<?=$tableName?>_changes()
	RETURNS trigger
AS
$BODY$
DECLARE
	changed_by_username_in text := NULL;
	changed_at_timestamp_in timestamp  := current_timestamp;
<?php foreach ($columns as $column) {
	print "\told_" . $column['column_name'] . "_in " . $column['data_type'] . " := NULL;\n";
	print "\tnew_" . $column['column_name'] . "_in " . $column['data_type'] . " := NULL;\n";
} ?>
	
	<?=$tableName?>_id INT := NULL;
	update_column_count INT := 0;
BEGIN
	-- This is a custom GUC which allows the current username to be set at the beginning
	-- of the request and isolated from other requests in the PostgreSQL session
	changed_by_username_in = current_setting('<?=getenv("PGDATABASE")?>.current_username');
	
	if (changed_by_username_in is null or changed_by_username_in = 'anonymous') THEN
		raise EXCEPTION 'Anonymous updates are not permitted for audited table <?=$tableName?>: Please set the GUC parameter <?=getenv("PGDATABASE")?>.current_username to a valid username';
	end if;
	
	IF (TG_OP = 'UPDATE') THEN
		<?=$tableName?>_id = OLD.id;
		
<?php foreach ($columns as $column) { ?>
		IF is_different(OLD.<?=$column['column_name']?>, NEW.<?=$column['column_name']?>) THEN
			old_<?=$column['column_name']?>_in := OLD.<?=$column['column_name']?>;
			new_<?=$column['column_name']?>_in := NEW.<?=$column['column_name']?>;
			update_column_count := update_column_count + 1;
		END IF;
<?php } ?>
	ELSIF (TG_OP = 'INSERT') THEN
		<?=$tableName?>_id = NEW.id;
<?php foreach ($columns as $column) { ?>
		new_<?=$column['column_name']?>_in := NEW.<?=$column['column_name']?>;
<?php } ?>
		update_column_count := update_column_count + 1;
	ELSIF (TG_OP = 'DELETE') THEN
		<?=$tableName?>_id = OLD.id;
<?php foreach ($columns as $column) { ?>
		old_<?=$column['column_name']?>_in := OLD.<?=$column['column_name']?>;
<?php } ?>
		update_column_count := update_column_count + 1;
	END IF;
	
	if update_column_count > 0 THEN
		insert into aud_<?=$tableName?>(
			id
			,changed_by_username
			,changed_at_timestamp
<?php foreach ($columns as $column) { ?>
			,old_<?=$column['column_name'] . "\n"?>
			,new_<?=$column['column_name'] . "\n"?>
<?php } ?>
		) values(
			nextval('aud_<?=$tableName?>_id_seq')
			,changed_by_username_in
			,changed_at_timestamp_in
<?php foreach ($columns as $column) { ?>
			,old_<?=$column['column_name']?>_in
			,new_<?=$column['column_name']?>_in
<?php } ?>
		);
	END IF;
	RETURN NULL;
END;
$BODY$
LANGUAGE plpgsql VOLATILE;
CREATE TRIGGER <?=$tableName?>_aud_insert_trigger AFTER INSERT ON <?=$tableName?> FOR EACH ROW EXECUTE PROCEDURE audit_<?=$tableName?>_changes();
CREATE TRIGGER <?=$tableName?>_aud_update_trigger AFTER UPDATE ON <?=$tableName?> FOR EACH ROW EXECUTE PROCEDURE audit_<?=$tableName?>_changes();
CREATE TRIGGER <?=$tableName?>_aud_delete_trigger AFTER DELETE ON <?=$tableName?> FOR EACH ROW EXECUTE PROCEDURE audit_<?=$tableName?>_changes();
