# create databases
CREATE DATABASE IF NOT EXISTS `lumen_db`;

# create lumen_db user and grant rights
CREATE USER 'lumen_db'@'db' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON *.* TO 'lumen_db'@'%';

