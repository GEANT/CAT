#!/bin/bash

# this script should run every hour to cleanup obsolete directories


script="$0"
basename="$(dirname $script)"

php $basename/cleanup.php
