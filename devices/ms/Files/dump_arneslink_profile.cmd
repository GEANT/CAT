@echo off
cd "%1"
netsh wlan export profile name="%2" folder="cat_tmp_dir"
cd cat_tmp_dir
rename *.xml a1.xml

