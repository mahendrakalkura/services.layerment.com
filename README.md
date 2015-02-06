How to install?
===============

Step 1
------

```
$ mkdir $HOME/services.layerment.com
$ cd $HOME/services.layerment.com
$ git clone --recursive git@bitbucket.org:kalkura/services.layerment.com.git .
$ chmod -R 0777 $HOME/services.layerment.com/var/cache
$ chmod -R 0777 $HOME/services.layerment.com/var/logs
$ chmod -R 0777 $HOME/services.layerment.com/var/stash
$ cp $HOME/services.layerment.com/parameters.php.sample $HOME/services.layerment.com/parameters.php
$ ln -s $HOME/services.layerment.com/web $HOME/public_html
```

How to run?
===========

```
$ cd $HOME/services.layerment.com
$ php -S 0.0.0.0:5000
```
