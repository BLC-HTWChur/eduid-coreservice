create table if not exists users
(
    user_uuid varchar(255) primary key,
    user_passwd varchar(255) not null
);

create table if not exists useridentities
(
    user_uuid varchar(255) not null,
    idp_uuid varchar(255), -- null is the core user
    userID varchar(255) not null, -- shiboleth id if available
    mailAddress varchar(256) not null,
    alias INTEGER NOT NULL DEFAULT 0 -- set to > 0 if the user has an alias
);


create table if not exists profiletokens (
    user_uuid varchar(255) not null,  -- user id
    token varchar(255) not null,      -- external token
    info TEXT                         -- use this to restric access to profile information
);

create table if not exists userprofiles
(
    user_uuid varchar(255) not null,
    userID varchar(255) not null,
    profile TEXT,
    isInvalid INTEGER
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

create table if not exists sessions
(
    token varchar(256) primary key,
    user_uuid varchar(64),
    lastaccess INTEGER
);

create table if not exists tokens (                -- OAUTH Token store
    token_id     varchar(255) PRIMARY KEY,         -- shared id (token in Bearer)
    token_type   varchar(255) NOT NULL,            -- type in [Bearer, Client, MAC]
    token_key    varchar(255),                     -- private key (only shared once)
    token_parent varchar(255),                     -- eduID specific OAuth Extension
    client_id    varchar(255),                     -- client identifier, e.g., device UUID
    user_uuid      varchar(255),                   -- internal user id if type = Bearer or MAC
    domain       varchar(255),                     -- reverse identifier of the app
    extra        TEXT                              -- extra token settings
);

create table if not exists services (
    uuid varchar(255) primary key,
    name varchar(255) not null,
    mainurl varchar(2048) not null,
    rsdurl varchar(2048) not null,
    info TEXT,
    token TEXT                                     -- our token for the service
);

create table if not exists serviceprotocols (
    service_uuid varchar(255) not null,
    name varchar(255) not null,
    rsd TEXT not null
);

create table if not exists serviceusers (
    service_uuid varchar(255) not null,
    user_uuid varchar(255) not null,
    last_access integer
);
