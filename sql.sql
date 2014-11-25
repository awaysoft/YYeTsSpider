CREATE TABLE movies
(
  mid serial NOT NULL,
  yid integer,
  name character varying(255),
  state character varying(100),
  year character varying(100),
  lang character varying(100),
  company character varying(100),
  starttime character varying(100),
  ename character varying(255),
  oname character varying(255),
  scriptwriter character varying(100),
  staring character varying(100),
  description text,
  image text,
  type integer, -- 类别...
  CONSTRAINT mid_pk PRIMARY KEY (mid)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE movies
  OWNER TO spider;
COMMENT ON COLUMN movies.type IS '类别
0: 电影
1: 电视剧
2: 纪录片
3: 公开课';

