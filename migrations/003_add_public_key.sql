-- add public key column for end-to-end encryption
ALTER TABLE users
    ADD COLUMN public_key TEXT DEFAULT NULL;
