CREATE TABLE movies
(
  mid serial NOT NULL,
  yid integer,
  name character varying(255),
  mtype character varying(50),
  state character varying(100),
  year character varying(100),
  lang character varying(100),
  company character varying(100),
  starttime character varying(100),
  ename character varying(255),
  oname character varying(255),
  scriptwriter character varying(500),
  director character varying(100),
  staring character varying(500),
  description text,
  image text,
  CONSTRAINT mid_pk PRIMARY KEY (mid)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE movies
  OWNER TO spider;


CREATE TABLE links
(
  lid serial NOT NULL,
  filename character varying(255),
  filesize character varying(20),
  format character varying(10),
  season character varying(100),
  emule character varying(1000),
  magnet character varying(1000),
  ct character varying(255),
  alllink text,
  mid integer,
  CONSTRAINT lid_pk PRIMARY KEY (lid)
)
WITH (
  OIDS=FALSE
);
ALTER TABLE links
  OWNER TO spider;

