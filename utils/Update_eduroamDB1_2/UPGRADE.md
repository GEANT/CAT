Description of the process of moving from dependence on eduroam DB v1 to v2-only - part of a process of upgrading from CAT 2.1.3 to 2.2
=======================================================================================================================================

1. Inspect config.php and enter database names you want tu use.
2. If your root has passwordless access to mysql then you can just run clean_databases.php as root,
   if not then add the root database password to line 17 - this script will clean up (or create if necessary) databases
   pointed to in config.php by 'eduroam', 'eduroamv2', 'eduroam_new'; it will also add required permissions to the
   database user listed in config/Master.php.
3. Run update_monitor_copy.php - it will take about 6 minutes, so be patient;
   this will fill the two temporary databases (specified in config.php as 'eduroam' and 'eduroamv2') with data.
4. Create a fresh database copy of CAT 2.1.3 in the database pointed to in config.php as 'cat' (the user pointed to
   in Master.php must have access rigts to this database.
5. Run sync_databases.php - this ma take over 10 minutes - this script intentionally does not delete the temporary databases
   so that you can repeat steps 4 and 5 without the need to run the previous ones.
6. Update the database schema using schema/2_1_3-2_2.sql.








