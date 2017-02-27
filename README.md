# H&O Importer

A Magento module and extension of the [AvS_FastSimpleImport][] module which allows you to map fields and import all sorts of file formats, data sources and data entities.

The module consists of various downloaders (http), source adapters (csv, spreadsheets, database or xml) and supports all entities that [AvS_FastSimpleImport][] supports (products, categories, customers) and last but not least allows you to field map all fields in from each format to the magento format.

All this configuration can be done using XML. You add the config to a config.xml and you can run the profile. The idea is that you set all the configuration in the XML and that you or the cron will run it with the perfect options.

Since the original target for the module was an import that could process thousands of products it is build with this in mind. It is able to process large CSV or XML files while using very little memory (think a few MB memory increase for processing a 1GB CSV file). See [Use cases](#use-cases)

We have chosen to do all configuration in XML, this makes the import profile way more maintainable, especially important when doing multiple imports for a single project.

To increase development and debugging speed there is a extensive shell tool that allows you to easily create new fieldmaps, add a downloader and start working.

![Terminal Preview](docs/images/terminal.png)
![Terminal Preview](docs/images/terminal2.png)

Example config for a customer import (this is added to the `<config><global><ho_import>` node:

```XML
<my_customer_import>
    <entity_type>customer</entity_type>
    <downloader model="ho_import/downloader_http">
        <url>http://google.nl/file.xml</url>
    </downloader>
    <decompressor model="ho_import/decompressor_zip">
        <source>var/import/Archief.zip</source>
        <target>var/import/extracted</target>
    </decompressor>
    <source model="ho_import/source_adapter_xml">
        <file>var/import/Klant.xml</file>
        <!--<rootNode>FMPDSORESULT</rootNode>-->
    </source>
    <import_options>
        <!--<continue_after_errors>1</continue_after_errors>-->
        <!--<ignore_duplicates>1</ignore_duplicates>-->
    </import_options>
    <events>
        <!--<source_row_fieldmap_before helper="ho_importinktweb/product::prepareRowCategory"/>-->
        <!--<import_before/>-->
        <!--<import_after/>-->
    </events>
    <fieldmap>
        <email field="Email"/>
        <_website helper="ho_import/import::getAllWebsites"/>
        <group_id helper="ho_import/import::getFieldMap">
            <field field="Status"/>
            <mapping>
                <particulier from="Particulier" to="1"/>
                <zakelijk from="Zakelijk" to="2"/>
            </mapping>
        </group_id>
        <prefix field="Voorletters"/>
        <firstname field="Voornaam" defaultvalue="ONBEKEND"/>
        <middlename field="Tussenvoegsel" />
        <lastname field="Achternaam" required="1"/>
        <company field="Bedrijfsnaam"/>
        <created_in value="old shop name"/>
        <taxvat field="BTWnummer" />
        <password field="cWachtWoord" />
        <gender helper="ho_import/import::getFieldMap">
            <field field="Geslacht"/>
            <mapping>
                <male from="M" to="male"/>
                <female from="V" to="female"/>
                <male_female from="M+V" to="male+female"/>
            </mapping>
        </gender>
    </fieldmap>
</my_customer_import>
```

## Installation

You can install the module via modman:
```bash
modman clone git@github.com:ho-nl/magento1-Ho_Import.git
```

Or you can [download the latest release](https://github.com/ho-nl/Ho_Import/archive/master.zip) it and place it in you Magento root.


## Getting started

### 1. Create a module
The idea is that you create a very light weight module for each project or import. This module has
all the config for that specific import.

_Need help creating an empty module for your installation, use a [module creator](http://www.silksoftware.com/magento-module-creator/).

Example config ([More import examples](#use-cases))

```XML
<config>
	<modules>
		<Ho_ImportJanselijn>
			<version>0.1.0</version>
		</Ho_ImportJanselijn>
	</modules>
	<global>
		<helpers>
            <ho_importjanselijn>
                <class>Ho_ImportJanselijn_Helper</class>
            </ho_importjanselijn>
        </helpers>

        <!-- ... -->

        <ho_import>
            <profile_name>
				<entity_type>customer</entity_type>
				<!-- ... the rest of the config -->
            </profile_name>
        </ho_import>
    </global>
</config>
```

### 2. Add the default config
_This section assumes that you place these config values in `<config><global><ho_import><my_import_name>`_

Add something like the following to your profile (see chapters below for detailed configuration):
```XML
<entity_type>customer</entity_type>
<downloader model="ho_import/downloader_http">
    <url>http://google.nl/file.xml</url>
</downloader>
<source model="ho_import/source_adapter_xml">
    <file>var/import/Klant.xml</file>
    <!--<rootNode>FMPDSORESULT</rootNode>-->
</source>
<import_options>
    <!--<continue_after_errors>1</continue_after_errors>-->
    <!--<ignore_duplicates>1</ignore_duplicates>-->
    <partial_indexing>1</partial_indexing>
</import_options>
```

### 3. Run the line shell utility
_Make sure you have cache disabled, because all XML is cached in Magento_

```bash
php hoimport.php -action line -profile profile_name
```

You'll see something like:
![Terminal Preview](docs/images/firstrun.png)

The first table shows the first line from the source file and the second table shows the results how they would be imported into Magento. It shows the error on each line where they are represented.

### 4. Expand all the fieldmaps to your liking
Grab an example that is most to your liking from the docs/imports folder and copy those fields to your config.

Now continue to map all your fields until you are satisfied.

### 5. Run the actual import
You can now import the complete set.

```bash
php hoimport.php -action import -profile profile_name -dryrun 1
```

*To just test if the import would run, add `-dryrun 1` to the command*

You will probably run into errors the first try. When the importer runs into errors it will return the faulty row. It will return the row that is imported (unfortunately it won't return the source row since that row isn't know at this point of the import).

If a specific sku, for example, is giving you trouble, you can run the line utility and do a search.

```bash
php hoimport.php -action line -profile profile_name -search sku=abd
```

### 6. Schedule an import (cronjob)
If you are satisfied with the import you can [add a schedule to it](#cron-schedule), this will add it to the cron
scheduler and run it at your configured time:

![Terminal Preview](docs/images/schedule.png)

As you can see, we have a `ho_import_schedule` cron which add the imports to the the cron and cleans up the cron if imports are removed/renamed. To speed up this process, you can run it manually.

## Config documentation
_This section assumes that you place these config values in `<config><global><ho_import><my_import_name>`_

### Supported Entity Types
All the entities of the [AvS_FastSimpleImport][] are supported:

- `catalog_product`
- `customer`
- `catalog_category`
- `catalog_category_product`

Example Config:
```XML
<entity_type>customer</entity_type>
```

### Cron schedule
Use the same formatting as the default cron setup.

Using a cron expression:
```XML
<schedule><cron_expr>0 2 * * *</cron_expr></schedule>
```

Using a config path:
```XML
<schedule><config_path>configuration/path/cron_expr</config_path></schedule>
```


### Import Options

All the options that are possible with the [AvS_FastSimpleImport][] are possible here as well:

```XML
<import_options>
    <skip_download>1</skip_download>
    <lock_attributes>1</lock_attributes>
    <archive_import_files>1</archive_import_files> <!-- archive files for later reference -->

    <!-- below are AvS_FastSimpleImport options. See http://avstudnitz.github.io/AvS_FastSimpleImport/options.html for more information and extra options. -->
	<error_limit>10000</error_limit>
    <continue_after_errors>1</continue_after_errors>
    <ignore_duplicates>1</ignore_duplicates>
    <allow_rename_files>0</allow_rename_files>
    <partial_indexing>1</partial_indexing>
    <dropdown_attributes>
        <country>country</country>
    </dropdown_attributes>
    <multiselect_attributes>
        <show_in_collection>show_in_collection</show_in_collection>
        <condition>condition</condition>
        <condition_label>condition_label</condition_label>
    </multiselect_attributes>
</import_options>
```

### Lock product/category attributes in backend
When you enable this option, a store admin can't edit the attributes that are imported by the
importer. Ho_Import is smart about this, it save the profile name with the product/category, so it only
locks the attributes which are set by the current importer. It also knows about store view specific values
imported.


Exampe config:

```XML
<import_options>
    <lock_attributes>1</lock_attributes>
</import_options>
```

When importing the name of a product it shows the attribute is locked
![Lock Attributes](docs/images/lock_attributes.png)


If you switch to a store view you can override the field:
![Lock Attributes](docs/images/lock_attributes2.png)

### Archive import files
After starting a new import the old import file gets renamed so it wont get deleted, easy for later referencing and
debugging.

```XML
<import_options>
    <archive_import_files>1</archive_import_files>
</import_options>
```

### Clean entities that are not in the source
Be able to automatically delete, hide or disable products and categories after importing. When a enabled, if a product
or category isn't in the source anymore it gets automatically deleted. Ho_Import tracks the entities from which profile
they came from:

![Profile information](docs/images/import_profile.png)

If a product for example isn't in the source any more AND it is the only associated profile with the entity it can be
cleaned up.

```XML
<clean>
    <mode>hide</mode> <!-- options are delete, hide, disable -->
</clean>
```


#### Multiple imports for the same product
If you have multiple imports for the same product (product information and stock information for
example), you have to define the profile associated with the product manually.

In your `<fieldmap>` node, add the following:
```XML
<ho_import_profile value="profile_one,profile_two"/>
```

If you have only one profile with `lock_attributes` enabled, this field gets filled automatically.

### Downloaders
The supported downloaders are HTTP and FTP.

#### HTTP Example (Low Memory)
```XML
<downloader model="ho_import/downloader_http">
    <url>http://google.nl/file.xml</url>

    <!-- the downloader defaults to var/import -->
    <!--<target>custom/download/path/filename.xml</target>-->
</downloader>
```

#### FTP Example (Low Memory)
```XML
<downloader model="ho_import/downloader_ftp">
    <host>ftp.website.com</host>
    <username>userr</username>
    <password>supersecurepassword</password>
    <file>httpdocs/file.xml</file> <!-- Relative path on the server, relative from the login -->
    <target>var/import/file.xml</target> <!-- Path relative from the Magento root -->
    <timeout>10</timeout> <!-- Optional: How long should we wait to connect -->
    <passive>0</passive> <!-- Optional: FTP transfer mode, by default it is set to passive, usually correct -->
    <ssl>1</ssl> <!-- Optional: For FTP with implicit SSL, this is NOT SFTP, which is FTP over SSH -->
    <file_mode>1</file_mode><!-- Optional: For FTP_ASCII or FTP_TEXT set value to 1, for FTP_BINARY or FTP_IMAGE leave empty. -->
</downloader>
```

#### Temporarily disable a download:
```XML
<import_options>
	<skip_download>1</skip_download>
</import_options>
```

### Decompressors
Decompress a file that has just been downloaded.

#### Zip Example (Low Memory)
```XML
<decompressor model="ho_import/decompressor_zip">
    <source>var/import/Archief.zip</source>
    <target>var/import/extracted</target> <!-- this is a folder, files inside the archive will be placed here -->
</decompressor>
```

### Sources
A source is a source reader. The source allows us to read data from a certain source. This could be
a file or it even could be a database.


#### CSV Source (Low Memory)
The CSV source is an implementation of PHP's [fgetcsv](http://php.net/manual/en/function.fgetcsv.php)


```XML
<source model="ho_import/source_adapter_csv">
    <file>var/import/customer.csv</file>

    <!-- the delimmiter and enclosure aren't required -->
    <delimiter>;</delimiter>
    <enclosure></enclosure>
    
    <!-- if the CSV has no header information, you can specify the column names -->
    <columns>
    	<sku/>
    	<other_columns/>
    </columns>
</source>
```


#### XML Source (Low Memory)
The XML source is loosely based on [XmlStreamer](https://github.com/prewk/XmlStreamer/blob/master/XmlStreamer.php).

```XML
<source model="ho_import/source_adapter_xml">
    <file>var/import/products.xml</file>

    <!-- If there is only one type of entity in the XML the custom rootNode isn't required. -->
    <rootNode>customRootNode</rootNode>

    <!-- You have the ability to define a custom childNode if the childNode isn't the direct ascendent of the rootNode -->
	<childNode>customChildNode</childNode>
</source>
```

*Note: It isn't tested if the childNode/rootNode is way down the document. The code is in place, but isn't tested. If you get the chance to test this please create an issue and let us know what you found.*

##### Example:

If you have the following XML file and you want to retrieve all the `<ARTICLE>` nodes:

```XML
<?xml version="1.0" encoding="utf-8"?>
<ARTICLES>
	<BODY>
		<COMPANY-NR>
			<COMPANY>10</COMPANY>
			<SHOP>
				<SHOPNR>2</SHOPNR>
				<ARTICLE>
					<!-- ... -->
				</ARTICLE>
				<ARTICLE>
                    <!-- ... -->
                </ARTICLE>
			</SHOP>
		</COMPANY-NR>
		<COMPANY-NR>
			<COMPANY>10</COMPANY>
            <SHOP>
                <SHOPNR>3</SHOPNR>
                <ARTICLE>
                    <!-- ... -->
                </ARTICLE>
            </SHOP>
		</COMPANY-NR>
	</BODY>
</ARTICLES>
```

This would result in the following configuration:

```XML
<source model="ho_import/source_adapter_xml">
    <file>path/to/you/file.xml</file>
    <rootNode>BODY</rootNode>
	<rootNode>ARTICLE</rootNode>
</source>
```


#### Spreadsheet Source (Low Memory)
The Spreadsheet Source is an implementation of [spreadsheet-reader](https://github.com/nuovo/spreadsheet-reader) and therefor supports

> So far XLSX, ODS and text/CSV file parsing should be memory-efficient. XLS file parsing is done with php-excel-reader from http://code.google.com/p/php-excel-reader/ which, sadly, has memory issues with bigger spreadsheets, as it reads the data all at once and keeps it all in memory.

```XML
<source model="ho_import/source_adapter_spreadsheet">
    <file>var/import/products.xml</file>

    <!-- If the first line has headers you can use that one, else the columns will only be numbered -->
    <!-- <has_headers>1</has_headers> -->
</source>
```


#### Database Source
The Database source is an implementation of `Zend_Db_Table_Rowset` and allows all implentation of `Zend_Db_Adapter_Abstract` as a source. It supports MSSQL, MySQL, PostgreSQL, SQLite and many others. For all possible supported databases take a look in `/lib/Zend/Db/Adapter`.

The current implementation isn't low memory because it executes the query and loads everything in memory.

```XML
<source model="ho_import/source_adapter_db">
    <host><![CDATA[hostname]]></host>
    <username><![CDATA[username]]></username>
    <password><![CDATA[password]]></password>
    <dbname><![CDATA[database]]></dbname>
    <model><![CDATA[Zend_Db_Adapter_Pdo_YourFavoriteDatabase]]></model>
    <pdoType>dblib</pdoType>
    <query><![CDATA[SELECT * FROM Customer]]></query>
    <!--<limit>10</limit>-->
    <!--<offset>10</offset>-->
</source>
```

If your PDO driver doesn't support `pdoType` then simply remove that node. If you wish to pass more config parameters to the PDO driver then add more nodes like for PGSQL: `<sslmode>require</sslmode>`


### Events
All events work with a transport object which holds the data for that line. This a `Varien_Object`
with the information set.

```XML
<events>
	<process_before helper="ho_import/import_product::prepareSomeData"/>
	<import_before helper="ho_import/import_product::callWifeIfItIsOk"/>
	<source_row_fieldmap_before helper="ho_import/import_product::checkIfValid"/>
	<source_row_fieldmap_after helper="ho_import/import_product::extractData"/>
	<source_fieldmap_after helper="ho_import/import_product::appendExtraData"/>
	<import_after helper="ho_import/import_product::reindexStuff"/>
	<process_after helper="ho_import/import_product::cleanupSomeData"/>
</events>
```

#### Event: `import_before`
- `object`: instance of `AvS_FastSimpleImport_Model_Import`

#### Event: `source_row_fieldmap_before`
It has one field `items` set. This can be replaced, extended etc. to manipulate the data. Optionally
you can set the key `skip` to `1` to skip this source row all together.

#### Event: `import_after`
- `object`: instance of `AvS_FastSimpleImport_Model_Import`
- `errors`: array of errors



### Fieldmap
This is where the magic of the module happens. Map a random source formatting to the Magento format.

The idea is that you specify the Magento format here and load the right values for each Magento
field, manipulate the data, etc. There is a syntax to handle the most easy cases and have the
ability to call an helper if that isn't enough.

| Reusing fieldmapped data. |
| ------------------------- |
| When importing mutations and having a complete import happens (complete runs every night for example, mutations every 15 minutes). You might want to use a different profile's fieldmapping. To do this you only need add `<fieldmap use="name_of_other_profile" />`. |


_This section assumes that you place these config values in `<config><global><ho_import><my_import_name><fieldmap>`_

#### Value
```XML
<tax_class_id value="2"/>
```

#### Field
```XML
<email field="Email"/>
```

In multi-level files like XML you can get a deeper value with a `/`

```XML
<email field="Customer/Email"/>
```

If there are attributes available, you can reach them with `@attributes`.

```XML
<sku field="@attributes/RECORDID"/>
```

#### Helper
Have the ability to call a helper method that generates the value. The contents of the field are the
arguments passed to the helper.

```XML
<_website helper="ho_import/import::getAllWebsites"><limit>1</limit></_website>
```

Calls the method in the class `Ho_Import_Helper_Import` with the first argument being the line and
the rest of the arguments being the contents in the node, in this case the limit.

```PHP
/**
 * Import the product to all websites, this will return all the websites.
 * @param array $line
 * @param $limit
 * @return array|null
 */
public function getAllWebsites($line, $limit) {
    if ($this->_websiteIds === null) {
        $this->_websiteIds = array();
        foreach (Mage::app()->getWebsites() as $website) {
            /** @var $website Mage_Core_Model_Website */

            $this->_websiteIds[] = $website->getCode();
        }
    }

    if ($limit) {
        return array_slice($this->_websiteIds, 0, $limit);
    }

    return $this->_websiteIds;
}
```

For more available helpers please see [Integrated helper methods](#integrated-helpers) and [Custom helper methods](#custom-helpers)

#### Use
Sometimes you want the same value multiple times in multiple fields. This loads the config of the
other fields and returns the result of that.

```XML
<image_label use="name"/>
```

#### Default value
```XML
<firstname field="First_Name" defaultvalue="UNKNOWN"/>
```

#### If field value
```XML
<company iffieldvalue="Is_Company" field="Company_Name"/>
```

#### Unless field value
The opposite of `iffieldvalue`

```XML
<firstname unlessfieldvalue="Is_Company" field="Customer_Name"/>
```

#### Required
Some fields are always required by the importer for each row. For example for products it is required that you
have the sku field always present.

```XML
<sku field="sku" required="1"/>
```

### Setting store view specific data
With simple additions to the config it is possible to set store view specific data. You have the exact same abilities as with normal fields, you only have to provide the `<store_view>` element with the fields for each storeview.

```XML
<description field="description_en">
    <store_view>
        <pb_de field="description_de"/>
        <pb_es field="description_es"/>
        <pb_fr field="description_fr"/>
        <pb_it field="description_it"/>
        <pb_nl field="description_nl"/>
    </store_view>
</description>
```

### Integrated helper methods <a name="integrated-helpers"></a>
There are a few helper methods already defined which allows you to do some common manipulation
without having to write your own helpers

#### getAllWebsites
```XML
<_website helper="ho_import/import::getAllWebsites">
	<limit>1</limit> <!-- optional -->
</_website>
```

#### findReplace
```XML
<short_description helper="ho_import/import::findReplace">
	<value field="sourceField"/>
    <findReplace>
        <doubleat find="@@" replace="@"/>
        <nbsp from="&nbsp;" replace=" "/>
    </findReplace>
    <trim>1</trim> <!-- optional -->
</short_description>
```

#### parsePrice
```XML
<price helper="ho_import/import::parsePrice">
    <pricefield field="PrijsVerkoop"/>
</price>
```

#### formatField
Implementation of [vsprinf](http://us1.php.net/vsprintf)

```XML
<meta_description helper="ho_import/import::formatField">
    <format>%s - For only €%s at Shop.com</format>
    <fields>
        <description field="Info"/>
        <price field="PrijsVerkoop"/>
    </fields>
</meta_description>
```

#### truncate
```XML
<description helper="ho_import/import::truncate">
    <value field="Info"/>
    <length>125</length>
    <etc>…</etc>
</description>
```

#### stripHtmlTags
```XML
<description helper="ho_import/import::stripHtmlTags">
    <value field="A_Xtratxt"/>
    <allowed><![CDATA[<p><a><br>]]></allowed>
</description>
```

#### getHtmlComment
Get a simple HTML comment (can't be added through XML due to XML limitations).

```XML
<description helper="ho_import/import::getHtmlComment">empty</description>
```

#### getFieldBoolean
```XML
<is_in_stock helper="ho_import/import::getFieldBoolean">
	<value field="stock"/>
</is_in_stock>
```

#### getFieldMultiple
Allow you to load multiple fields. Each field has the same abilities as a normal field (allows you
to call a helper, value, field, iffieldvalue, etc.

```XML
<_address_prefix helper="ho_import/import::getFieldMultiple">
    <fields>
        <billing iffieldvalue="FactAdres" field="Voorvoegsel"/>
        <shipping iffieldvalue="BezAdres" field="Voorvoegsel"/>
    </fields>
</_address_prefix>
```


#### getFieldLimit
Implements [array_slice](http://us.php.net/array_slice).

```XML
<image helper="ho_import/import::getFieldLimit">
    <field use="_media_image"/>
    <limit value="1"/> <!-- optional -->
    <offset value="1"/> <!-- optional -->
</image>
```


#### getFieldCombine
Get multiple fields and glue them together

```XML
<sku helper="ho_import/import::getFieldCombine">
    <fields>
        <prefix value="B"/>
        <number field="BmNummer"/>
    </fields>
    <glue>-</glue> <!-- optional, defaults to a space -->
</sku>
```

#### getFieldSplit
Split a field into multiple pieces

```XML
<_category helper="ho_import/import::getFieldSplit">
    <field field="category"/>
    <split>***</split>
</_category>
```

#### getFieldMap
```XML
<gender helper="ho_import/import::getFieldMap">
    <value field="Geslacht"/>
    <mapping>
        <male from="M" to="male"/>
        <female from="V" to="female"/>
    </mapping>
</gender>
```

#### getFieldCounter
```XML
<_media_position helper="ho_import/import::getFieldCounter">
    <countfield field="cImagePad"/>
</_media_position>
```

#### ifFieldsValue
You can normally define `iffieldvalue='fieldname'` to do simple value checking. Something you need
to check multiple fields.

```XML
<billing_first_name helper="ho_postbeeldproduct/import_customer::ifFieldsValue">
    <fields>
        <billing_first_name field="billing_first_name"/>
        <billing_last_name field="billing_last_name"/>
        <billing_address field="billing_address"/>
        <billing_city field="billing_city"/>
        <billing_country_code field="billing_country_code"/>
    </fields>
    <billing field="billing_first_name"/>
</billing_first_name>
```

#### getMediaAttributeId (@deprecated in 1.5, use getAttributeId)
Usually used in combination with a counter to set the correct getMediaAttributeId

```XML
<_media_attribute_id helper="ho_import/import::getFieldCounter">
    <countfield field="cImagePad"/>
    <fieldvalue helper="ho_import/import::getMediaAttributeId"/>
</_media_attribute_id>
```


#### getAttributeId
Get an attribute's ID.

```XML
<field helper="ho_import/import::getAttributeId">
    <attribute value="media_gallery"/>
</field>
```


#### getMediaImage
Download the image from a remote URL and place it in the `media/import` folder.

```XML
<image helper="ho_import/import::getMediaImage">
    <imagefield field="cImagePad"/> <!-- URL to image -->
    <limit>1</limit>
    <filename use="sku"/> <!-- optional, when the server doesn't give back readable image names -->
    <extension value="jpg"/> <!-- optional, when the URL doesn't end in a filename -->
</image>
```

Download images from FTP:

```
ftp://username:password@ftp.domain.com/path/on/ftp/image.png
```

#### timestampToDate
Parse a timestamp and output in the Magento running format, just specify in which timezone the  current date is. Add an offset with one of the [Relative Formats](http://am1.php.net/manual/en/datetime.formats.relative.php).

```XML
<news_to_date helper="ho_import/import::timestampToDate">
    <field field="entry_date"/>
    <timezoneFrom>Europe/Amsterdam</timezoneFrom>
    <offset>3 day</offset>
</news_to_date>
```

#### getCurrentDate
Returns the current date

```XML
<news_from_date helper="ho_import/import::getCurrentDate"/>
```

#### Product: getUrlKey
```XML
<url_key helper="ho_import/import_product::getUrlKey">
    <fields>
        <name field="Titel"/>
    </fields>
    <glue>-</glue>
</url_key>
```


#### Product: getAvailableUrlKey
Get an URL key that does not exist. The first 2 parameters will always be used. Every parameter after the second one is optional.
On the first attempt, the first 2 parameters will be combined, if this url key isn't available anymore, it will try a second attempt with the third parameter.
This process will continue until all parameters have been used or an available url key has been found.

```XML
<url_key helper="ho_import/import_product::getAvailableUrlKey"> 
    <ident use="sku"/> 
    <name use="name"/> 
    <!-- additional fields --> 
    <ean field="ean"/> 
</url_key>
```

#### getSpecialFromDate
Give the price and the special_price and it returns the current date if the special_price is lower that the price (and not empty).

```XML
<special_from_date helper="ho_import/import_product::getSpecialFromDate">
    <price use="price"/>
    <special_price use="special_price"/>
</special_from_date>
```

#### getSpecialPrice
Give the price and the special_price and it returns the special_price if the special_price is lower that the price (and not empty).

```XML
<special_price helper="ho_import/import_product::getSpecialPrice">
    <price use="price"/>
    <special_price field="special_price"/>
</special_price>
```


#### getSpecialPrice

#### Category: getUrlKey
```XML
<url_key helper="ho_import/import_category::getUrlKey">
    <fields>
        <name field="Titel"/>
    </fields>
    <glue>-</glue>
</url_key>
```

#### Customer: mapCountryIso3ToIso2
```XML
<billing helper="ho_import/import_customer::mapCountryIso3ToIso2">
    <field field="billing_country_code"/>
</billing>
```

#### Customer: mapCountryIso2ToIso3
```XML
<billing helper="ho_import/import_customer::mapCountryIso2ToIso3">
    <field field="billing_country_code"/>
</billing>
```


### Combining helper methods <a name="combining-helpers"></a>
The output of a helper method can be used as the input of another helper. This way you can combine multiple helpers to achieve your task.

**Example:**  
In the following example we have multiple fields that are used for the `_media_image` field:

```XML
<_media_image helper="ho_import/import::getMediaImage">
    <!-- We use the result of getFieldMultiple as the first argument for the `getMediaImage` helper. -->
    <images helper="ho_import/import::getFieldMultiple">
        <fields>
            <field field="image"/>
            <!-- we are using the result of getFieldSplit as the input for getFieldMultiple -->
            <media_gallery helper="ho_import/import::getFieldSplit"> 
                <field field="media_gallery"/>
                <split>,</split>
            </media_gallery>
            <awards helper="ho_import/import::getFieldSplit">
                <field field="awards"/>
                <split>,</split>
            </awards>
        </fields>
    </images>
    <limit/>
    <!-- We use result of `getUrlKey` as the third argument for the `getMediaImage` helper. -->
    <!-- We make sure the name of the image is the same as the SKU of the product, gets automatically appended with -1, -2, etc. -->
    <filename helper="ho_import/import_product::getUrlKey">
        <fields><name use="sku"/></fields>
        <glue>-</glue>
    </filename>
</_media_image>
```

### Custom helper methods <a name="custom-helpers"></a>
Not every situation is a simple value processing and more complex logic might have to be used. You have the ability to easily create your own helper methods for each project. Simply create your own helper class and call that class.

Example: To determine if an address is a default address we create the two fields:

```XML
<_address_default_billing_  helper="ho_importjanselijn/import_customer::getAddressDefaultBilling"/>
<_address_default_shipping_ helper="ho_importjanselijn/import_customer::getAddressDefaultShipping"/>
```

And create a helper class which with the methods:

```PHP
class Ho_ImportJanselijn_Helper_Import_Customer extends Mage_Core_Helper_Abstract
{

    public function getAddressDefaultBilling($line) {
        if ($line['InvAddress']) { //there is a billing and shipping address
            return array(1,0);
        } else { //there is only a shipping address
            return 1;
        }
    }

    public function getAddressDefaultShipping($line) {
        if ($line['InvAddress']) { //there is a billing and shipping address
            return array(0,1);
        } else { //there is only a shipping address
            return 1;
        }
    }
}
```

As you can see it sometimes returns an array of values and sometimes just returns a value. If you helper method returns an array of values Ho_Imports [internally rewrites those multiple values to multiple import rows](https://github.com/ho-nl/Ho_Import/tree/master/app/code/community/Ho/Import/Model/Import.php#L470).

## Configurable products
It is possible to create configurable products using `Ho_Import`.
```XML
<fieldmap...>
<configurable_builder>
    <sku helper="ho_intersteelimport/import_configurable::getSku"> 
        <simpleSku field="artikelnummer"/>
        <titel field="titel_product"/>
    </sku>
    <attributes helper="ho_intersteelimport/import_configurable::getAttributes"> <!-- returns an array of the attributes used to create the configurable -->
        <simpleName field="titel_product"/>
    </attributes>
    <calculate_price>1</calculate_price>
    <calculate_price_in_stock>1</calculate_price_in_stock>
    <fieldmap>
        <name helper="ho_intersteelimport/import_configurable::getName">
            <simpleName field="titel_product"/>
            <pcmaat use="pcmaat"/>
        </name>
        <description use="description"/>
        <url_key helper="ho_import/import_product::getAvailableUrlKey">
            <ident use="sku"/>
            <url_key helper="ho_import/import::getFieldCombine">
                <fields>
                    <sku use="sku"/>
                    <productgroup field="productgroup"/>
                </fields>
                <glue> </glue>
            </url_key>
            <!-- additional fields -->
            <ean field="ean_nummer"/>
        </url_key>
        <_type value="configurable"/>
        <visibility value="4"/>
        <is_in_stock value="1"/>
    </fieldmap>
</configurable_builder>
```
You might have noticed that we used the `use` attribute here as well. The `use` attribute will use the value from your fieldmapping, not the the value from the configurable builder.

### sku
This will determine the SKU value for the configurable product

### attributes
This option will combine the simple products into a configurable products. The value of this attribute will be configurable.
<br>
Multiple attributes are allowed.

### calculate_price
Set this option to `1` and Ho_Import will try and determine the lowest price for your configurable product.

### calculate_price_in_stock
> Note: The `calculate_price` option has to be enabled in order to use this.

Set this option to `1` and Ho_Import will try and determine the lowest price based on products in stock for your configurable product.

## CLI / Shell Utility
The importer comes with a shell utility where you'll be spending most of your time.

### line
```
php hoimport.php  -action line
	-profile profile_name             Available profiles:    [list of profiles]
	-skip_download 1                  Skip the download
	-skip_decompress 1                Skip the decompressing of the downloaded file
	-line 1,2,3                       Commaseparated list of lines to be checked
	-search sku=abd                   Search for the value of a field.
```

### import
```
php hoimport.php -action import
	-profile profile_name             Available profiles:    [list of profiles]
	-skip_download 1                  Skip the download
	-skip_decompress 1                Skip the decompressing of files
	-dryrun 1                         Validate all data agains the Magento validator, but do not import anything
```

### importCsv
```
php hoimport.php -action csv
	Debug method: doesn't fieldmap, only imports the current csv
	-profile profile_name             Available profiles:    [list of profiles]
	-dryrun 1                         Validate all data agains the Magento validator, but do not import anything
```

## Logging
There are two logging modes: CLI and cron mode. In the CLI mode it always logs to the CLI and tries
to add nice colors, etc. In the cron-mode it will log to the the log files and can also log to the
messages inbox in the admin panel.

## Calling importer in your code
This is pretty easy to do:

```PHP
protected function _importCustomers($memberIds) {
    Mage::getModel('ho_import/import')
        ->setProfile('postbeeld_customers')
        ->setSourceOptions(array('member_id' => implode(',', $memberIds)))
        ->process();

    Mage::helper('ho_import/log')->done();
    return $this;
}
```


### File logging
Every import run by the cron is saved in `var/ho_import.log`.

### Admin Panel notification


Sometimes you want to put a message in the Admin panel if an error pops up. By default the system
only creates an admin panel message if there is a warning.

```PHP
EMERG   = 0;  // Emergency: system is unusable
ALERT   = 1;  // Alert: action must be taken immediately
CRIT    = 2;  // Critical: critical conditions
ERR     = 3;  // Error: error conditions
WARN    = 4;  // Warning: warning conditions
NOTICE  = 5;  // Notice: normal but significant condition
INFO    = 6;  // Informational: informational messages
DEBUG   = 7;  // Debug: debug messages
SUCCESS = 8;  // Success: When everything is going well.
```

Place these config values in `<config><global><ho_import><my_import_name>` to change the level when
and admin panel message will be added.

```XML
<log_level>6</log_level>
```


## Use cases <a name="use-cases"></a>
At the time of release we have this tool running for multiple clients, multiple types of imports:
- Multiple sources per product [Example config](docs/imports/product_multiple_sources.xml)
- One time product / category imports from an old datasource [Example config](docs/imports/old_products.xml)
- Periodic category import with values for multiple store views [Example config](docs/imports/categories_with_store_view_data.xml)
- 15 minute inventory only updates
- Nightly complete inventory updates [Example config](docs/imports/product_stock_multiple.xml)
- Nightly price updates
- Incremental category/product updates from ERP systems
- Customer import [Example config](docs/imports/customer_import.xml)
- Customer import with billing and shipping address [Example config](docs/imports/customer_import_billing_shipping.xml)

## Performance
We don't have actual benchmarks at the moment, but the time spend fieldmapping is an order of magnitude faster than the actual import its self.

## License
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

## Support
If you need help with the module, create an issue in the [GitHub issue tracker](https://github.com/ho-nl/Ho_Import/issues).

## Author
The module is written by Paul Hachmang (twitter: [@paales](https://twitter.com/paales), email: paul@h-o.nl) build for H&O, we make [Magento Webshops](https://www.h-o.nl/magento-webshops) (website: <http://www.h-o.nl/>, email: <info@h-o.nl>, twitter: [@ho_nl](https://twitter.com/ho_nl)).

## Why build it and open source it?
After having build multiple product, category and customer imports I was never really satisfied with the available projects. After implementing a project with bare code we came to the conclusion that it was pretty difficult to create an import, make sure al the fields are correctly set for Magento to accept them, the development iteration was to slow, etc.

After building this we think we made a pretty good module that has value for a lot of Magento developers, so releasing it open source was natural. And with the combined effort of other developers, we can improve it even further, fix bugs, add new features etc.

[AvS_FastSimpleImport]: https://github.com/avstudnitz/AvS_FastSimpleImport "AvS_FastSimpleImport by @avstudnitz"
