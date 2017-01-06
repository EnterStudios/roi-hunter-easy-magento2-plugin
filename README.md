# Google Remarketing by ROI Hunter Easy
[ROI Hunter Easy](http://easy.roihunter.com/) helps power your Magento 2.x eshop with Google Remarketing. We know it's a pain to get dynamic remarketing up and running on Google – developers need to produce an xml feed in a certain format, you need to register it in Google Merchant, correct wrong product formats, link Merchant Center to Adwords, ask developers to put all codes to right places, set up audiences in Adwords, connect everything together, go through dozens of settings, etc. Time to stop! We are putting an end to this hassle. ROI Hunter Easy will take care of all the annoying tech settings so you can have professional remarketing campaigns in 3 simple steps:

* Install the plugin to your Magento 2.x eshop for Free
* Connect Google Adwords
* Create your first Campaign

You do not need any technical requirements. ROI Hunter Easy does all settings for you

**A separate Google Adwords account is required. ROI Hunter Easy is free. You only pay for your running ads directly to Google. You can set up your budget directly in ROI Hunter Easy.** You can create your Google Adwords account here: http://www.google.cz/adwords/

**ROI Hunter will automatically do these things for you:**

* create product catalogue for your website
* register your website to [Google Merchant center](https://www.google.cz/retail/merchant-center/)
* upload your product catalogue to Google
* verify your website
* connect your [Adwords account](https://www.google.cz/adwords/) with [Google Merchant center](https://www.google.cz/retail/merchant-center/)
* deploy all dynamic remarketing scripts to your website
* set up automatically the most effective remarketing audiences
* set up the most effective bidding strategy
* choose the most effective dynamic banner/text templates.

Plugin reflects our best practices from 8 years of advertising experience in **Google Adwords**. 

ROI Hunter Easy marketing extension is available in **EN**. Support is also available in **EN**.

**Pricing of extension:** 

* Extension cost: 0.00 €
* Extension update: 0.00 €
* No support fee
* No contract duration

\* Extension do not cover your Google Adwords Ad Spend. 


# Installation
There are two possible ways for extension installation:

## Magento Component Manager
Using Component Manager is official way for magento plugin installation. You can find this plugin on the Magento Marketplace here: [ROI Hunter Easy plugin](https://marketplace.magento.com/businessfactory-roi-hunter-easy.html). 

More info about Component Manager on the official pages http://devdocs.magento.com/guides/v2.1/comp-mgr/module-man/compman-main-pg.html#compman-access-new.

If you can't open Component Manager check for a solution at https://github.com/magento/magento2/issues/4159.

## Manual installation
Manual installation is faster than installation by Component Manager. Also your shop won't be offline during it. On the other hand it requires admin access to your Magento server.

Simply download the latest stable source code and copy them to the following folder on your server: `<magento_installation_path>/app/code/`. The final structure will look like this:
[![source_code_path.png](https://s30.postimg.org/guisj1an5/source_code_path.png)](https://postimg.org/image/agtpfs5r1/)

After that, you must run below command to register the extension:  
`php <magento_installation_path>/bin/magento setup:upgrade`

Plugin installation should be completed now. But it is always a good idea to deploy a static content and clear the Cache. Static content can be deployed by following command:  
`php <magento_installation_path>/bin/magento setup:static-content:deploy`  
The Cache can be cleared after you log into the admin panel and go to the SYSTEM -> Cache Management. You can refer to the below screenshot.

[![flush_magento_cache.png](https://s30.postimg.org/67ievnspd/flush_magento_cache.png)](https://postimg.org/image/l3gy3943x/)


# FAQ
**How to reset plugin? How to restore stucked feed generation?**

If you need to return the plugin to its original settings (for example when you used wrong Google account during login) you can delete the data as follows: Go to the Admin section - STORES - Configuration - ROI HUNTER - ROI HUNTER EASY  - Reset Data. On the same screen you can also restore stucked feed generation.
[![config_screen.png](https://s30.postimg.org/q0ogjzzf5/config_screen.png)](https://postimg.org/image/wr4xtfmkt/)

# Support
If you would have any difficulty with the usage of this extension, or have any issues you would like to raise with us please feel free to submit a support ticket by emailing easy@roihunter.com.
