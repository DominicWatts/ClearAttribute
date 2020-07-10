# ClearAttribute

![phpcs](https://github.com/DominicWatts/ClearAttribute/workflows/phpcs/badge.svg)

![PHPCompatibility](https://github.com/DominicWatts/ClearAttribute/workflows/PHPCompatibility/badge.svg)

![PHPStan](https://github.com/DominicWatts/ClearAttribute/workflows/PHPStan/badge.svg)

Shell script to null product attribute data from database using direct queries - quickly clear down database for specific product attributes

# Install instructions #

`composer require dominicwatts/clearattribute`

`php bin/magento setup:upgrade`

# Usage instructions #

`xigen:clearattribute:null <attribute>`

`bin/magento xigen:clearattribute:null special_price`
