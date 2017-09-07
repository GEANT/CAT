<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

echo "<pre>";
print_r(\core\User::findLoginIdPByEmail(filter_input(INPUT_GET, 'mail', FILTER_SANITIZE_EMAIL)));
echo "<pre>";