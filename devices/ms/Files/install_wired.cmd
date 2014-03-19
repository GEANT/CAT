@echo off
netsh lan set eapuserdata filename="user_cred.xml" allusers=no interface="*"
del  user_cred.xml