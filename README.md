# postgresql-table-auditing
This repository demonstrates how to create tables and triggers which automatically audit all changes to tables in a PostgreSQL database

Prior to running the generate.php command, you will need to set the following environment variables:
* PGUSER
* PGPASSWORD
* PGDATABASE
* PGHOST
* PGPORT

Addition documentation for these environment variables can be found here:
https://www.postgresql.org/docs/9.1/static/libpq-envars.html

Once these environment variables have been set, you will need to define a custom GUC parameter at the bottom of your postgresql.conf file and restart your database. The convention used by the script is that the namespace of the GUC parameter is the same as the database name so it is recommended that you adhere to this convention as well. For example, you could add this parameter by adding the following to the bottom of your postgresql.conf:
```
mydatabase.current_username = 'anonymous'
```

Once the above has been done, you can run the following command,
```bash
./generate.php mytable
```

The necessary SQL will be emitted to the screen for creating the table aud_mytable in addition to the triggers necessary for populating it. If you aren't concerned with reviewing the output, you can generate and run the sql as follows:
```bash
./generate.php mytable | psql
```

This works because the connection parameters have already been set in the PG environment variables. After the triggers have been created, you will notice that you receive an error similar to "*Anonymous updates are not permitted for audited table mytable: Please set the GUC parameter mydatabase.current_username to a valid username*" when modifying mytable via INSERT/UPDATE/DELETE. This is because the audit triggers are responsible for capturing the WHAT, WHEN, and WHO of all modifications to audited tables. The WHAT and the WHEN are easy but, particularly in web applications which employ a shared database user, the trigger does not have access to the WHO. To get around this, we set the custom GUC session variable to the username of the user which is currently utilizing the database connection. The triggers can then access this variable to obtain and record the identity of the person making the change. So, to alter mytable we now have to do the following:
```sql
set mydatabase.current_username = 'myuser';
update mytable set myfield = 'new value' where id = 1;
set mydatabase.current_username = 'anonymous';
```

It is intended that this approach be used conjunction with HTTP request scoped database transactions similar to the following:
```php
<?php
  // TODO: Perform setup

  try {
    $connection->begin();
    $connection->execute("set mydatabase.current_username='myuser'");

    // TODO: Make database changes here

    $connection->commit();
  } catch (Exception $e) {
    $connection->rollback();
  } finally {
    $connection->execute("set mydatabase.current_username='anonymous'");
  }
?>
```
These examples assume that you have, at least, PHP 7.0.18 and PostgreSQL 9.1
