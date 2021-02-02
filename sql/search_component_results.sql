create table results_by_component (
  id int not null auto_increment,
  position int not null,
  term varchar(255) not null,
  component varchar(255) not null,
  score float not null,
  file_page varchar(255) not null,
  image_url varchar(255) not null,
  skipped tinyint(1) default 0,
  rating tinyint(1) default null,
  search_date datetime default CURRENT_TIMESTAMP,
  primary key (id)
) engine=innodb;

create index term_rating on results_by_component(term, rating);
