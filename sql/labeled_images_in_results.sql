drop table if exists labeledResult;
drop table if exists resultset;
drop table if exists search;

create table search (
  id int not null auto_increment,
  description varchar(255) not null,
  ts timestamp default CURRENT_TIMESTAMP,
  primary key (id)
) engine=innodb;

create table resultset (
  id int not null auto_increment,
  searchId int not null,
  term varchar(255) not null,
  resultCount int not null,
  primary key (id),
  foreign key (searchId) references search(id)
) engine=innodb;

create table labeledResult (
  id int not null auto_increment,
  resultsetId int not null,
  filePage varchar(255) not null,
  position tinyint not null,
  rating tinyint not null,
  primary key (id),
  foreign key (resultsetId) references resultset(id)
) engine=innodb;