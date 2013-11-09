# H&O Importer

An extension of the [AvS_FastSimpleImport](https://github.com/avstudnitz/AvS_FastSimpleImport) that allows you to import abitrary file formats, sources and entities.

The module consists of various downloaders (http), source adapters (csv, spreadsheets, database or xml) and supports all entities that AvS_FastSimpleImport supports (products, categories, customers) and last but not least allows you to field map all fields in from each format to the magento format.

All this configuration can be done using XML. You add the config to a config.xml and you can run the profile. The idea is that you set all the configuration in the XML and that you or the cron will run it with the perfect options.

Since the original target for the module was an import that could process thousands of products it is build with this in mind. It is able to process large CSV or XML files while using very little memory (think 10MB memory for processing a 1GB CSV file, *actual benchmarks required*).

To increase development and debugging speed there is a extensive shell tool that allows you to easily create new fieldmaps, add a downloader and start working.

--- ADD SCREENSHOT ---

Example config for a customer import (this is added to the `<config><global><ho_import>` node:

```XML
<my_customer_import>
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
    </import_options>
    <events>
        <!--<source_row_fieldmap_before helper="ho_importinktweb/product::prepareRowCategory"/>-->
        <!--<before_import/>-->
        <!--<after_import/>-->
    </events>
    <fieldmap>
        <email field="Email"/>
        <_website helper="ho_importjanselijn/import_customer::getWebsites"/>
        <group_id helper="ho_import/import::getFieldMap">
            <field>Status</field>
            <mapping>
                <particulier from="Particulier" to="1"/>
                <zakelijk from="Zakelijk" to="2"/>
            </mapping>
        </group_id>
        <prefix field="Voorletters"/>
        <firstname helper="ho_import/import::getFieldDefault">
            <field>Voornaam</field>
            <default>ONBEKEND</default>
        </firstname>
        <middlename field="Tussenvoegsel" />
        <lastname field="Achternaam" required="1"/>
        <company field="Bedrijfsnaam"/>
        <created_in helper="ho_importjanselijn/import_customer::getCreatedIn"/>
        <taxvat field="BTWnummer" />
        <password field="cWachtWoord" />
        <gender helper="ho_import/import::getFieldMap">
            <field>Geslacht</field>
            <mapping>
                <male from="M" to="male"/>
                <female from="V" to="female"/>
                <male_female from="M+V" to="male+female"/>
            </mapping>
        </gender>
        <z_klant_id field="zKlantID"/>
        <note field="Bijzonderheden"/>

        <_address_prefix helper="ho_import/import::getFieldMultiple">
            <fields>
                <billing iffieldvalue="FactAdres" field="Voorvoegsel"/>
                <shipping iffieldvalue="BezAdres" field="Voorvoegsel"/>
            </fields>
        </_address_prefix>
        <_address_firstname helper="ho_import/import::getFieldMultiple">
            <fields>
                <billing iffieldvalue="FactAdres" field="Voornaam" defaultvalue="ONBEKEND"/>
                <shipping iffieldvalue="BezAdres" field="Voornaam" defaultvalue="ONBEKEND"/>
            </fields>
        </_address_firstname>
        <_address_middlename helper="ho_import/import::getFieldMultiple">
            <fields>
                <billing iffieldvalue="FactAdres" field="Tussenvoegsel"/>
                <shipping iffieldvalue="BezAdres" field="Tussenvoegsel"/>
            </fields>
        </_address_middlename>
        <_address_lastname helper="ho_import/import::getFieldMultiple">
            <fields>
                <billing iffieldvalue="FactAdres" field="Achternaam"/>
                <shipping iffieldvalue="BezAdres" field="Achternaam"/>
            </fields>
        </_address_lastname>
        <_address_company helper="ho_import/import::getFieldMultiple">
            <fields>
                <billing iffieldvalue="FactAdres" field="Bedrijfsnaam"/>
                <shipping iffieldvalue="BezAdres" field="Bedrijfsnaam"/>
            </fields>
        </_address_company>
        <_address_street helper="ho_import/import::getFieldMultiple">
            <fields>
                <billing iffieldvalue="FactAdres" field="FactAdres" defaultvalue="ONBEKEND"/>
                <shipping iffieldvalue="BezAdres" field="BezAdres"  defaultvalue="ONBEKEND"/>
            </fields

        </_address_street>
        <_address_postcode helper="ho_import/import::getFieldMultiple">
            <fields>
                <billing iffieldvalue="FactAdres" field="FactPostcode" defaultvalue="ONBEKEND"/>
                <shipping iffieldvalue="BezAdres" field="BezPostcode"  defaultvalue="ONBEKEND"/>
            </fields>
        </_address_postcode>
        <_address_city helper="ho_import/import::getFieldMultiple">
            <fields>
                <billing iffieldvalue="FactAdres" field="FactPlaats" defaultvalue="ONBEKEND"/>
                <shipping iffieldvalue="BezAdres" field="BezPlaats"  defaultvalue="ONBEKEND"/>
            </fields>
        </_address_city>
        <_address_country_id helper="ho_import/import::getFieldMultipleMap">
            <fields>
                <billing  iffieldvalue="FactAdres" field="FactLand"/>
                <shipping iffieldvalue="BezAdres" field="BezLand"/>
            </fields>
            <mapping>
                <empty from="" to="NL"/>
                <belgie from="België" to="BE"/>
            </mapping>
        </_address_country_id>
        <_address_telephone helper="ho_import/import::getFieldMultiple">
            <fields>
                <billing iffieldvalue="FactAdres" field="Telefoon" value="-"/>
                <shipping iffieldvalue="BezAdres" field="Telefoon" value="-"/>
            </fields>
        </_address_telephone>
        <_address_telephone_alt helper="ho_import/import::getFieldMultiple">
            <fields>
                <billing iffieldvalue="FactAdres" field="Telefoon2"/>
                <shipping iffieldvalue="BezAdres" field="Telefoon2"/>
            </fields>
        </_address_telephone_alt>
        <_address_default_billing_  helper="ho_importjanselijn/import_customer::getAddressDefaultBilling"/>
        <_address_default_shipping_ helper="ho_importjanselijn/import_customer::getAddressDefaultShipping"/>
    </fieldmap>
</my_customer_import>
```

## Config documentation
This section assumes that you place these config values in `<config><global><ho_import><my_import_name>`

### Supported Entity Types
All the entities of the AvS_FastSimpleImport are supported:

- `catalog_product`
- `customer`
- `catalog_category`

Example Config:
```XML
<entity_type>customer</entity_type>
```


### Downloaders
The only current supported downloader is HTTP. New downloaders can be easily created.

#### HTTP Example (:white_check_mark: Low Memory)
```XML
<downloader model="ho_import/downloader_http">
    <url>http://google.nl/file.xml</url>

    <!-- the downloader defaults to var/import -->
    <!--<target>custom/download/path</target>-->
</downloader>
```


#### Temporarily disable a download:
```XML
<import_options>
	<skip_download>1</skip_download>
</import_options>
```

### Sources
A source is a source reader. The source allows us to read data from a certain source. This could be
a file or it even could be a database.


#### CSV Source (:white_check_mark: Low Memory)
The CSV source is an implementation of PHP's [fgetcsv](http://php.net/manual/en/function.fgetcsv.php)


```XML
<source model="ho_import/source_adapter_csv">
    <file>var/import/customer.csv</file>

    <!-- the delimmiter and enclosure aren't required -->
    <!--<delimiter>;</delimiter>-->
    <!--<enclosure></enclosure>-->
</source>
```


#### XML Source (:white_check_mark: Low Memory)
The XML source is loosely based on [XmlStreamer](https://github.com/prewk/XmlStreamer/blob/master/XmlStreamer.php).
```XML
<source model="ho_import/source_adapter_xml">
    <file>var/import/products.xml</file>

    <!-- If there is only one type of entity in the XML the custom rootnode isn't required -->
    <!-- <rootNode>customRootNode</rootNode> -->
</source>
```


#### Spreadsheet Source (:white_check_mark: Low Memory)
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
The Database source is an implementation of `Zend_Db_Table_Rowset` and allows all implentation of `Zend_Db_Adapter_Abstract` as a source. For all possible supported databases take a look in `/lib/Zend/Db/Adapter`.

The current implementation isn't low memory because it executes the query an loads everything in memory. In a low memory implementation it would work with pages of lets say a 1000.

```XML
<source model="ho_import/source_adapter_db">
    <host><![CDATA[hostname]]></host>
    <username><![CDATA[username]]></username>
    <password><![CDATA[password]]></password>
    <dbname><![CDATA[database]]></dbname>
    <model><![CDATA[Zend_Db_Adapter_Pdo_Mssql]]></model>
    <pdoType>dblib</pdoType>
    <query><![CDATA[SELECT * FROM Customer]]></query>
    <!--<limit>10</limit>-->
    <!--<offset>10</offset>-->
</source>
```


### Import Options

All the options that are possible with the AvS_FastSimpleImport are possible here as well:

```XML
<import_options>
	<error_limit>10000</error_limit>
	<continue_after_errors>1</continue_after_errors>
	<ignore_duplicates>1</ignore_duplicates>
	<dropdown_attributes>one,two,three</dropdown_attributes>
	<multiselect_attributes>four,five</multiselect_attributes>
	<allow_rename_files>0</allow_rename_files>
	<partial_indexing>1</partial_indexing>
</import_options>
```

### Events
All events work with a transport object which holds the data for that line. This a `Varien_Object`
with the information set.

#### source_row_fieldmap_before
- `items`
- `skip`

#### after_import
- `object`

#### before_import
- `object`

### Fieldmap
This is perhaps the most interesting

--- TODO ---

## License
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

## Author & Contributors
Paul Hachmang - [H&O](http://www.h-o.nl/)
