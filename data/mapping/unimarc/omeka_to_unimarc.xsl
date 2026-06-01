<?xml version="1.0" encoding="UTF-8"?>
<!--
    Postprocess XSL : transforme le pivot DC produit par
    omeka_to_unimarc.xml en MARCXML-slim avec datafields UNIMARC.

    Sortie :
      <record xmlns="http://www.loc.gov/MARC21/slim">
        <leader>     nam a22     uu 4500</leader>
        <controlfield tag="001">{identifier}</controlfield>
        <datafield tag="200" ind1="1" ind2=" "><subfield code="a">{title}</subfield></datafield>
        <datafield tag="210" ind1=" " ind2=" ">
          <subfield code="c">{publisher}</subfield>
          <subfield code="d">{date}</subfield>
        </datafield>
        <datafield tag="215" ind1=" " ind2=" "><subfield code="a">{format}</subfield></datafield>
        <datafield tag="300" ind1=" " ind2=" "><subfield code="a">{description}</subfield></datafield>
        <datafield tag="606" ind1=" " ind2=" "><subfield code="a">{subject}</subfield></datafield>
        <datafield tag="700" ind1=" " ind2="1"><subfield code="a">{creator}</subfield></datafield>
        <datafield tag="702" ind1=" " ind2="1"><subfield code="a">{contributor}</subfield></datafield>
        <datafield tag="856" ind1=" " ind2=" "><subfield code="u">{source}</subfield></datafield>
      </record>
-->
<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcp="urn:omeka-mapper:dc-pivot"
    xmlns="http://www.loc.gov/MARC21/slim"
    exclude-result-prefixes="dc dcp">

    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>

    <xsl:template match="/">
        <record>
            <leader>     nam a22     uu 4500</leader>

            <xsl:if test="dcp:dc-pivot/dc:identifier">
                <controlfield tag="001">
                    <xsl:value-of select="dcp:dc-pivot/dc:identifier[1]"/>
                </controlfield>
            </xsl:if>

            <xsl:for-each select="dcp:dc-pivot/dc:title">
                <datafield tag="200" ind1="1" ind2=" ">
                    <subfield code="a"><xsl:value-of select="."/></subfield>
                </datafield>
            </xsl:for-each>

            <xsl:if test="dcp:dc-pivot/dc:publisher or dcp:dc-pivot/dc:date">
                <datafield tag="210" ind1=" " ind2=" ">
                    <xsl:for-each select="dcp:dc-pivot/dc:publisher">
                        <subfield code="c"><xsl:value-of select="."/></subfield>
                    </xsl:for-each>
                    <xsl:for-each select="dcp:dc-pivot/dc:date">
                        <subfield code="d"><xsl:value-of select="."/></subfield>
                    </xsl:for-each>
                </datafield>
            </xsl:if>

            <xsl:for-each select="dcp:dc-pivot/dc:format">
                <datafield tag="215" ind1=" " ind2=" ">
                    <subfield code="a"><xsl:value-of select="."/></subfield>
                </datafield>
            </xsl:for-each>

            <xsl:for-each select="dcp:dc-pivot/dc:description">
                <datafield tag="300" ind1=" " ind2=" ">
                    <subfield code="a"><xsl:value-of select="."/></subfield>
                </datafield>
            </xsl:for-each>

            <xsl:for-each select="dcp:dc-pivot/dc:subject">
                <datafield tag="606" ind1=" " ind2=" ">
                    <subfield code="a"><xsl:value-of select="."/></subfield>
                </datafield>
            </xsl:for-each>

            <xsl:for-each select="dcp:dc-pivot/dc:creator">
                <datafield tag="700" ind1=" " ind2="1">
                    <subfield code="a"><xsl:value-of select="."/></subfield>
                </datafield>
            </xsl:for-each>

            <xsl:for-each select="dcp:dc-pivot/dc:contributor">
                <datafield tag="702" ind1=" " ind2="1">
                    <subfield code="a"><xsl:value-of select="."/></subfield>
                </datafield>
            </xsl:for-each>

            <xsl:for-each select="dcp:dc-pivot/dc:source">
                <datafield tag="856" ind1=" " ind2=" ">
                    <subfield code="u"><xsl:value-of select="."/></subfield>
                </datafield>
            </xsl:for-each>

        </record>
    </xsl:template>

</xsl:stylesheet>
