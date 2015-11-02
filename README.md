# **[CLOSED] apigenerator.org has been discontinued**

Install
-------

Clone the apigenerator project on your server and install dependencies.

```bash
git clone git@github.com:apigenerator/apigenerator.org.git /path/to/apigenerator.org
cd /path/to/apigenerator.org
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

Setup your web server to point to `/path/to/apigenerator.org/web`.

Setup ssh key
-------------

As your website user run `ssh-keygen` and generate a new ssh key pair.
Add the public key to your github profile.

Hint: Make sure, the private key is protected against external access!

Setup git
---------

As your website user
run `git config --global user.email "info@my-apigen-hook.org"`
and `git config --global user.name "My Name"`
to configure commit messages.
