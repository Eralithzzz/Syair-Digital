CREATE TABLE BASE_USER (
    userId NUMBER PRIMARY KEY,
    email VARCHAR2(100) NOT NULL UNIQUE,
    name VARCHAR2(100) NOT NULL,
    username VARCHAR2(50) NOT NULL UNIQUE,
    password VARCHAR2(255) NOT NULL,
    role VARCHAR2(20) CHECK (role IN ('Users', 'Admin'))
);

CREATE TABLE USERS (
    userId NUMBER PRIMARY KEY,
    FOREIGN KEY (userId) REFERENCES BASE_USER(userId)
);

CREATE TABLE ADMIN (
    userId NUMBER PRIMARY KEY,
    FOREIGN KEY (userId) REFERENCES BASE_USER(userId)
);

CREATE TABLE SYAIR (
    syairId NUMBER PRIMARY KEY,
    title VARCHAR2(200) NOT NULL,
    content CLOB NOT NULL,
    author VARCHAR2(100) NOT NULL,
    yearPublish DATE,
    userId NUMBER NOT NULL,
    FOREIGN KEY (userId) REFERENCES BASE_USER(userId)
);

CREATE TABLE STATUS (
    statusId NUMBER PRIMARY KEY,
    syairId NUMBER NOT NULL,
    currentStatus VARCHAR2(20) CHECK (currentStatus IN ('Pending', 'Approved', 'Rejected')),
    updated_by NUMBER,
    status_date TIMESTAMP,
    FOREIGN KEY (syairId) REFERENCES SYAIR(syairId)
);

CREATE TABLE BOOKMARK (
    bookmarkId NUMBER PRIMARY KEY,
    userId NUMBER NOT NULL,
    syairId NUMBER NOT NULL,
    dateBookmark TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES BASE_USER(userId),
    FOREIGN KEY (syairId) REFERENCES SYAIR(syairId),
    UNIQUE (userId, syairId)
);

CREATE TABLE LIKED (
    likedId NUMBER PRIMARY KEY,
    userId NUMBER NOT NULL,
    syairId NUMBER NOT NULL,
    dateLikes TIMESTAMP,
    FOREIGN KEY (userId) REFERENCES BASE_USER(userId),
    FOREIGN KEY (syairId) REFERENCES SYAIR(syairId),
    UNIQUE (userId, syairId)
);

CREATE SEQUENCE baseuser_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE users_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE admin_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE syair_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE status_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE bookmark_seq START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE liked_seq START WITH 1 INCREMENT BY 1;

-- Drop dependent tables first
--DROP TABLE BOOKMARK CASCADE CONSTRAINTS;
--DROP TABLE LIKED CASCADE CONSTRAINTS;
--DROP TABLE STATUS CASCADE CONSTRAINTS;
--DROP TABLE SYAIR CASCADE CONSTRAINTS;
--DROP TABLE USERS CASCADE CONSTRAINTS;
--DROP TABLE ADMIN CASCADE CONSTRAINTS;
--DROP TABLE BASE_USER CASCADE CONSTRAINTS;
--
---- Drop sequences
--DROP SEQUENCE liked_seq;
--DROP SEQUENCE bookmark_seq;
--DROP SEQUENCE status_seq;
--DROP SEQUENCE syair_seq;
--DROP SEQUENCE admin_seq;
--DROP SEQUENCE users_seq;
--DROP SEQUENCE baseuser_seq;

INSERT INTO BASE_USER VALUES (
    baseuser_seq.NEXTVAL,
    'admin@syair.com',
    'System Admin',
    'admin',
    'admin123', -- sample only
    'Admin'
);

INSERT INTO BASE_USER VALUES (
    baseuser_seq.NEXTVAL,
    'ali@gmail.com',
    'Ali Ahmad',
    'ali',
    'ali123',
    'Users'
);

INSERT INTO BASE_USER VALUES (
    baseuser_seq.NEXTVAL,
    'siti@gmail.com',
    'Siti Aminah',
    'siti',
    'siti123',
    'Users'
);

INSERT INTO ADMIN VALUES (1);
INSERT INTO USERS VALUES (2);
INSERT INTO USERS VALUES (3);
INSERT INTO SYAIR VALUES (
    syair_seq.NEXTVAL,
    'Syair Perjuangan',
    'Ini adalah syair tentang perjuangan bangsa.',
    'Ali Ahmad',
    DATE '2023-05-01',
    2
);

INSERT INTO SYAIR VALUES (
    syair_seq.NEXTVAL,
    'Syair Kehidupan',
    'Syair ini menggambarkan kehidupan seharian.',
    'Siti Aminah',
    DATE '2024-02-15',
    3
);
INSERT INTO STATUS VALUES (
    status_seq.NEXTVAL,
    1,
    'Pending',
    NULL,
    SYSTIMESTAMP
);

INSERT INTO STATUS VALUES (
    status_seq.NEXTVAL,
    2,
    'Approved',
    1,
    SYSTIMESTAMP
);
INSERT INTO BOOKMARK VALUES (
    bookmark_seq.NEXTVAL,
    2,
    2,
    SYSTIMESTAMP
);

INSERT INTO BOOKMARK VALUES (
    bookmark_seq.NEXTVAL,
    3,
    1,
    SYSTIMESTAMP
);
INSERT INTO LIKED VALUES (
    liked_seq.NEXTVAL,
    2,
    1,
    SYSTIMESTAMP
);

INSERT INTO LIKED VALUES (
    liked_seq.NEXTVAL,
    3,
    2,
    SYSTIMESTAMP
);
COMMIT;
























