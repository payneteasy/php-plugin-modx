--------------------
paynet / mspPaynetEasy
--------------------

"PaynetEasy Payment" module for ModX Revolution Minishop2

INSTALL
Connect via FTP to the server and place the folder “mspPaynetEasy” with the plugin in the root folder of the site
In your browser, type https://website.com/mspPaynetEasy/_build/build.transport.php for package information and starting install plugin.
Authorize as “Administrator”
Hover your cursor over the “Packages” menu item. Then “miniShop2” and select “Settings”.
Go to the “Payment Methods” tab
For the created payment method “PaynetEasy Payment” select the action “Change”.
Go to the “Delivery options” tab and enable the available options for this payment system.
Hover over the “Packages” menu item. Next “miniShop2” and select “System Settings”.
In the key search field, specify “paynet” and press “Enter”
In the “Value” column, clicking twice on the desired parameter will open the input field. Fill in all plugin settings
To configure redirection after checkout, enter page id for “Successful payment page” and page id for “Unsuccessful payment page”
If the payment method does not appear in the cart, check if the payment method is enabled on the settings page.

UNINSTALL
Authorize as “Administrator”
Move the cursor to the menu item “Packages”. Next “miniShop2” and select “Settings”
Find “PaynetEasy” and select the “Delete” action
Go to the server and delete the folder “mspPaynetEasy” from the root folder of the site
Delete the file on the server “payneteasy.class.php” at site_folder/core/components/minishop2/custom/payment/
Delete the file on the “payneteasy.php” server at the address site_folder/assets/components/minishop2/payment/
Delete the file on the server “payneteasy-setting-fields.combo.js” at site_folder/assets/components/csf/js/mgr
Delete the file on the server “msp.payneteasy.inc.php” at site_folder/core/components/minishop2/lexicon/en
Delete the file on the server “msp.payneteasy.inc.php” at site_folder/core/components/minishop2/lexicon/ru

© Pyaneteasy