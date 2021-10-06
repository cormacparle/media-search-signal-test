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
  language varchar(5) not null,
  resultCount int not null,
  searchExecutionTime_ms int unsigned not null,
  primary key (id),
  foreign key (searchId) references search(id)
) engine=innodb;

create table labeledResult (
  id int not null auto_increment,
  resultsetId int not null,
  filePage varchar(255) not null,
  position smallint not null,
  score decimal(10, 5) not null,
  rating tinyint not null,
  primary key (id),
  foreign key (resultsetId) references resultset(id)
) engine=innodb;

create index resultsetId_rating_position_score on labeledResult(resultsetId, rating, position, score);
