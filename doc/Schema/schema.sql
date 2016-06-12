create table if not exists tokens (
    kid varchar(255) PRIMARY KEY,
    token_type varchar(255) NOT NULL,
    access_key varchar(255),
    mac_key varchar(255),                           -- shared secret
    mac_algorithm varchar(10),                      -- only hmac-sha-1 or hmac-sha-256
    seq_nr INTEGER DEFAULT 1,                       -- 0 if no sequence is used

    issued_at INTEGER,
    expires INTEGER DEFAULT 0,
    consumed INTEGER DEFAULT 0,
    max_seq INTEGER DEFAULT 0,
    last_access INTEGER DEFAULT 0,

    user_uuid varchar(255),                         -- used for federation users
    service_uuid varchar(255),                      -- used for federation services
    client_id varchar(255),                         -- used for apps and external services

    parent_kid varchar(255),
    scope TEXT,                                     -- optional value
    extra TEXT
);

create table if not exists users
(
    user_uuid varchar(255) primary key,
    user_passwd varchar(255) not null,
    salt varchar(50) not null
);

create table if not exists useridentities
(
    user_uuid varchar(255) not null,
    idp_uuid varchar(255),    -- null is the core user
    userid varchar(255),      -- external shiboleth id if available
    mailaddress varchar(256) not null,
    extra TEXT,               -- all other profile fields
    invalid INTEGER DEFAULT 0 -- if the IDP revokes the identity
);

create table if not exists profiletokens (
    user_uuid varchar(255) not null,  -- user id
    token varchar(255) not null,      -- external token
    info TEXT                         -- use this to restric access to profile information
);

create table if not exists identityproviders
(
    uuid varchar(255) primary key,
    name varchar(255) not null,
    mainurl varchar(2048) not null,
    idurl varchar(2048),
    rsdurl varchar(2048),
    token TEXT,                                    -- our token to access the IDP
    info TEXT,
    rsd TEXT
);

create table if not exists services (
    service_uuid varchar(255) primary key,
    name varchar(255) not null,
    mainurl varchar(2048) not null,
    token_endpoint varchar(2048),
    rsdurl varchar(2048) not null,
    info TEXT,
    token TEXT                                     -- private service token
);

create table if not exists serviceprotocols (
    service_uuid varchar(255) not null,
    rsd TEXT not null,
    last_update INTEGER DEFAULT 0
);

create table if not exists protocolnames (
    service_uuid varchar(255) not null,
    rsd_name varchar(255) not null
);

create table if not exists serviceusers (
    service_uuid varchar(255) not null,
    user_uuid varchar(255) not null,
    last_access integer
);

create table if not exists federation_users (
    user_uuid varchar(255) not null
);
