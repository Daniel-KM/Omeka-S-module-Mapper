<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:mods="http://www.loc.gov/mods/v3"
    exclude-result-prefixes="dc">

    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>

    <!-- Pass-through for unknown nodes. -->
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>

    <!-- Rename dc:title -> mods:title. -->
    <xsl:template match="dc:title">
        <mods:title>
            <xsl:value-of select="."/>
        </mods:title>
    </xsl:template>
</xsl:stylesheet>
