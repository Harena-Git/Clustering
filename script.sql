CREATE DATABASE cluster_session ;

create user 'cluster'@'%' identified by 'cluster';  

create user 'cluster'@'localhost' identified by 'cluster';  

GRANT ALL PRIVILEGES ON cluster_session.* to 'cluster'@'%';

GRANT ALL PRIVILEGES ON cluster_session.* to 'cluster'@'localhost';

exit;


use cluster_session;

create table if not exists php_session(
    id VARCHAR(255) primary key,
    data text ,
    date_change timestamp default current_timestamp on update current_timestamp  
) ;     