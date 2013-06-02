Install or update apigen
------------------------

Download a standalone archive from https://github.com/apigen/apigen/downloads and extract into the project root.
The file `./apigen/apigen.php` must exist.

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
