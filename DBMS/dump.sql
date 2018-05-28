CREATE TABLE garaze (
    id_garaz           SERIAL PRIMARY KEY,
    poznamka           VARCHAR(30),
    limit_min          INTEGER NOT NULL,
    limit_max          INTEGER NOT NULL,
    id_malina   	   INTEGER NOT NULL,
);

CREATE TABLE garaze_historie (
    cas                   TIMESTAMP NOT NULL,
	id_garaz       		  INTEGER NOT NULL,
    id_stav   			  INTEGER NOT NULL
);

ALTER TABLE garaze_historie ADD CONSTRAINT garaze_historie_pk PRIMARY KEY ( garaze_id_garaz,
garaze_stav_id_stav,
cas );
	
CREATE TABLE garaze_stav (
    id_stav   SERIAL PRIMARY KEY,
    stav      VARCHAR(40) NOT NULL
);

CREATE TABLE maliny (
    id_malina   SERIAL PRIMARY KEY,
    poznamka    VARCHAR(30),
    last_seen   TIMESTAMP NOT NULL,
	login       VARCHAR(60) NOT NULL,
    heslo       VARCHAR(60) NOT NULL --must use PASSWORD_BCRYPT
);

ALTER TABLE maliny ADD CONSTRAINT maliny_login_un UNIQUE ( login );

CREATE TABLE uzivatele (
    prezdivka     VARCHAR(40) NOT NULL,
    id_uzivatel   SERIAL PRIMARY KEY,
    user_login    VARCHAR(60) NOT NULL,
    heslo         VARCHAR(60) NOT NULL
);
ALTER TABLE uzivatele ADD CONSTRAINT uzivatele__un UNIQUE ( user_login );

ALTER TABLE maliny
    ADD CONSTRAINT maliny_uzivatele_fk FOREIGN KEY (id_uzivatel)
        REFERENCES uzivatele (id_uzivatel);
		
ALTER TABLE garaze_historie
    ADD CONSTRAINT garaze_historie_garaze_fk FOREIGN KEY ( id_garaz )
        REFERENCES garaze ( id_garaz );

ALTER TABLE garaze_historie
    ADD CONSTRAINT garaze_historie_garaze_stav_fk FOREIGN KEY (id_stav )
        REFERENCES garaze_stav ( id_stav );

ALTER TABLE garaze
    ADD CONSTRAINT garaze_maliny_fk FOREIGN KEY (id_malina)
        REFERENCES maliny (id_malina );
