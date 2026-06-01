<?xml version="1.0" encoding="UTF-8"?>
<!--
    Postprocess XSL : transforme le pivot DC produit par
    omeka_to_mods.xml en MODS 3.7.

    Entrée :
      <dcp:dc-pivot xmlns:dcp="urn:omeka-mapper:dc-pivot"
                    xmlns:dc="http://purl.org/dc/elements/1.1/">
        <dc:title>...</dc:title>
        <dc:creator>...</dc:creator>
        ...
      </dcp:dc-pivot>

    Sortie :
      <mods:mods xmlns:mods="http://www.loc.gov/mods/v3" version="3.7">
        <mods:titleInfo><mods:title>...</mods:title></mods:titleInfo>
        <mods:name type="personal">
          <mods:namePart>...</mods:namePart>
          <mods:role>
            <mods:roleTerm authority="marcrelator" type="text">creator</mods:roleTerm>
          </mods:role>
        </mods:name>
        ...
      </mods:mods>
-->
<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcp="urn:omeka-mapper:dc-pivot"
    xmlns:mods="http://www.loc.gov/mods/v3"
    exclude-result-prefixes="dc dcp">

    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>

    <xsl:template match="/">
        <mods:mods version="3.7">
            <xsl:apply-templates select="dcp:dc-pivot/dc:title"/>
            <xsl:apply-templates select="dcp:dc-pivot/dc:creator">
                <xsl:with-param name="role" select="'creator'"/>
            </xsl:apply-templates>
            <xsl:apply-templates select="dcp:dc-pivot/dc:contributor">
                <xsl:with-param name="role" select="'contributor'"/>
            </xsl:apply-templates>
            <xsl:apply-templates select="dcp:dc-pivot/dc:type"/>
            <xsl:if test="dcp:dc-pivot/dc:publisher or dcp:dc-pivot/dc:date">
                <mods:originInfo>
                    <xsl:for-each select="dcp:dc-pivot/dc:publisher">
                        <mods:publisher><xsl:value-of select="."/></mods:publisher>
                    </xsl:for-each>
                    <xsl:for-each select="dcp:dc-pivot/dc:date">
                        <mods:dateIssued><xsl:value-of select="."/></mods:dateIssued>
                    </xsl:for-each>
                </mods:originInfo>
            </xsl:if>
            <xsl:apply-templates select="dcp:dc-pivot/dc:subject"/>
            <xsl:apply-templates select="dcp:dc-pivot/dc:description"/>
            <xsl:apply-templates select="dcp:dc-pivot/dc:language"/>
            <xsl:apply-templates select="dcp:dc-pivot/dc:rights"/>
            <xsl:apply-templates select="dcp:dc-pivot/dc:identifier"/>
        </mods:mods>
    </xsl:template>

    <xsl:template match="dc:title">
        <mods:titleInfo>
            <mods:title><xsl:value-of select="."/></mods:title>
        </mods:titleInfo>
    </xsl:template>

    <xsl:template match="dc:creator|dc:contributor">
        <xsl:param name="role"/>
        <mods:name type="personal">
            <mods:namePart><xsl:value-of select="."/></mods:namePart>
            <mods:role>
                <mods:roleTerm authority="marcrelator" type="text">
                    <xsl:value-of select="$role"/>
                </mods:roleTerm>
            </mods:role>
        </mods:name>
    </xsl:template>

    <xsl:template match="dc:type">
        <mods:typeOfResource><xsl:value-of select="."/></mods:typeOfResource>
    </xsl:template>

    <xsl:template match="dc:subject">
        <mods:subject>
            <mods:topic><xsl:value-of select="."/></mods:topic>
        </mods:subject>
    </xsl:template>

    <xsl:template match="dc:description">
        <mods:abstract><xsl:value-of select="."/></mods:abstract>
    </xsl:template>

    <xsl:template match="dc:language">
        <mods:language>
            <mods:languageTerm><xsl:value-of select="."/></mods:languageTerm>
        </mods:language>
    </xsl:template>

    <xsl:template match="dc:rights">
        <mods:accessCondition><xsl:value-of select="."/></mods:accessCondition>
    </xsl:template>

    <xsl:template match="dc:identifier">
        <mods:identifier><xsl:value-of select="."/></mods:identifier>
    </xsl:template>

</xsl:stylesheet>
