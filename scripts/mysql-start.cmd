@echo off
"%LOCALAPPDATA%\ClinicTools\mysql-extracted\PFiles64\MySQL\MySQL Server 8.4\bin\mysqld.exe" --no-defaults --basedir="%LOCALAPPDATA%\ClinicTools\mysql-extracted\PFiles64\MySQL\MySQL Server 8.4" --datadir="%LOCALAPPDATA%\ClinicTools\mysql-data" --bind-address=127.0.0.1 --port=3306 --mysqlx=OFF --console
