<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

/*
 * Run this script after the DB schema update is complete. It converts multilang
 * attributes from "serialize()" to proper DB columns.
 */

require_once("../config/_config.php");
require_once("DBConnection.php");

CONST TREATMENT_TABLES = ['federation_option', 'institution_option', 'profile_option', 'user_options'];
CONST TREATMENT_COLUMNS = ['row', 'row', 'row', 'id'];

$dbInstance = DBConnection::handle('INST');

$treatment_options = [];

$optionsInNeed = $dbInstance->exec("SELECT name FROM profile_option_dict WHERE flag = 'ML'");
while ($optionsResultRow = mysqli_fetch_object($optionsInNeed)) {
    $treatment_options[] = $optionsResultRow->name;
}
foreach (TREATMENT_TABLES as $tableIndex => $tableName) {
    foreach ($treatment_options as $optionName) {
        $affectedPayloads = $dbInstance->exec("SELECT ".TREATMENT_COLUMNS[$tableIndex]." AS row, option_lang, option_value FROM $tableName WHERE option_name = '$optionName'");
        if ($affectedPayloads === FALSE) {
            echo "[FAIL] Unknown error querying update status for option " . $optionName . " in table $tableName. Did you run the 'ALTER TABLE' statements?\n";
            continue;
        }
        while ($oneAffectedPayload = mysqli_fetch_object($affectedPayloads)) {
            if ($oneAffectedPayload->option_lang !== NULL) {
                echo "[SKIP] The option in row " . $oneAffectedPayload->row . " of table $tableName appears to be converted already. Not touching it.\n";
                continue;
            }
            $decoded = unserialize($oneAffectedPayload->option_value);
            if ($decoded === FALSE || !isset($decoded["lang"]) || !isset($decoded['content'])) {
                echo "[WARN] Please check row " . $oneAffectedPayload->row . " of table $tableName - this entry did not successfully unserialize() even though it is a multi-lang attribute!\n";
                continue;
            }
            // pry apart lang and content into their own columns
            $rewrittenPayload = $dbInstance->exec("UPDATE $tableName SET option_lang = ?, option_value = ? WHERE ".TREATMENT_COLUMNS[$tableIndex]." = " . $oneAffectedPayload->row, "ss", $decoded["lang"], $decoded["content"]);
            if ($rewrittenPayload !== FALSE) {
                echo "[ OK ] " . $oneAffectedPayload->option_value . " ---> " . $decoded["lang"] . " # " . $decoded["content"] . "\n";
                continue;
            }
            echo "[FAIL] Unknown error executing the payload update for row " . $oneAffectedPayload->row . " of table $tableName. Did you run the 'ALTER TABLE' statements?\n";
        }
    }
}