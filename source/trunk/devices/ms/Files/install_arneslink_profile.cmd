cd "%1\cat_tmp_dir"
copy a1.xml b%4.xml
powershell -Command "( gc 'b%4.xml') | ForEach-Object { $_ -replace 'UTF-16','UTF-8' -replace '<name>%2</name>', '<name>%3</name>' -replace '<hex>.*</hex>','' } | sc 'c%4.xml'"
netsh wlan add profile filename="c%4.xml"
