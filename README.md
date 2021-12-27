# Wordpress 4.X to 5.8.2 Woo-Commerce compatible

# <h3>How To Generate Access token and API Secret :</h3>
You can generate Or Regenerate Access token and API Secret from login into your paykun admin panel, Then Go To : Settings -> Security -> API Keys. There you will find the generate button if you have not generated api key before.

If you have generated api key before then you will see the date of the api key generate, since you will not be able to retrieve the old api key (For security reasons) we have provided the re-generate option, so you can re-generate api key in case you have lost the old one.

Note : Once you re-generate api key your old api key will stop working immediately. So be cautious while using this option.

# <h3>Prerequisite</h3>
    Merchant Id (Please read 'How To Generate Access token and API Secret :')
    Access Token (Please read 'How To Generate Access token and API Secret :')
    Encryption Key (Please read 'How To Generate Access token and API Secret :')
    Wordpress 4.x to 5.0.2 compatible Woo-Commerce version must be installed and other payment method working properly.

# <h3>Installation</h3>

  1. Download the zip and extract it to the some temporary location
  2. Copy the folder named 'paykun' from the extracted zip into the directory location /wp-content/plugins/
  3. Activate the plugin through the left side 'Plugins' menu in WordPress.
  4. Visit the Woo-Commerce => settings page, and click on the 'Payments' tab.
  5. Now you will find paykun payment method make it enable and click on 'Manage' button at the right side.
  6. Click on Paykun to edit the settings.
  7. Enter all the required details provided by paykun.
  8. Now you can see Paykun in your payment option.
  9. Save the below configuration.

      * Enable                  - Enable check box
      * Title                   - Paykun
      * Description             - Default
      * Merchant Id             - Staging/Production Merchant Id provided by Paykun
      * Access Token            - Staging/Production Access Token provided by Paykun
      * Encryption Key          - Staging/Production Encryption Key provided by Paykun
      * Return Page             - My Account
      * Log (yes/no)            - For trouble shooting    

  10. Your Woo-commerce plug-in is now installed. You can accept payment through Paykun.

#<h3> In case of any query, please contact to support@paykun.com.</h3>