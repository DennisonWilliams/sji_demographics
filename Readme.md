# Custom SJI Demographics
This module will print out a custom demographics "card" within the patient
demographics page (aka participant chart cover).  It will also hide the 
deault demographics card.

### Installing Module Via Composer
You can do it with the following command:
```
composer config repositories.repo-name vcs https://github.com/DennisonWilliams/sji_demographics
```

At that point you can run the install command
```
composer require DennisonWilliams/sji_demographics
```

### Activating Your Module
Install your module using either composer.

Once your module is installed you can activate your module in OpenEMR by doing the following.

  1. Login to your OpenEMR installation as an administrator
  2. Go to your menu and select Modules -> Manage Modules
  3. Click on the Unregistered tab in your modules list
  4. Find your module and click the *Register* button.  This will reload the page and put your module in the Registered list tab of your modules
  5. Now click the *Install* button next your module name.
  6. Finally click the *Enable* button for your module.
