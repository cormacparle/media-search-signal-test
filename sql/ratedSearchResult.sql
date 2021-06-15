drop table if exists ratedSearchResult;
drop table if exists tag;
drop table if exists ratedSearchResult_tag;

create table ratedSearchResult (
  id int not null auto_increment,
  searchTerm varchar(255) not null,           # the search term
  language varchar(5) not null default 'en',  # the ISO code for the language of the search term
  result varchar(255) not null,               # file page title string for the result
  rating tinyint default null,                # -1 if result is a bad match, 0 if it is indifferent, +1 if it is good, null when not yet scored
  primary key(id),
  unique index (searchTerm, language, result)
) engine=innodb;

create index term_rating on ratedSearchResult(searchTerm, rating);

create table tag (
  id int not null auto_increment,
  text varchar(255) unique not null,          # the tag name
  primary key(id)
) engine=innodb;

insert into tag values (1, 'media-search-signal-test');
insert into tag values (2, 'image-recommendation-test');

create table ratedSearchResult_tag (
  ratedSearchResultId int not null,
  tagId int not null,
  unique key (ratedSearchResultId, tagId)
) engine=innodb;
