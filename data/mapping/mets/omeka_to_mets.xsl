<?xml version="1.0" encoding="UTF-8"?>
<!--
    Postprocess XSL : transforme le pivot DC produit par
    omeka_to_mets.xml en un document METS minimal.

    Sortie :
      <mets:mets xmlns:mets="http://www.loc.gov/METS/">
        <mets:metsHdr CREATEDATE="..."/>
        <mets:dmdSec ID="dmd1">
          <mets:mdWrap MDTYPE="DC">
            <mets:xmlData>
              <dc:title>...</dc:title>
              ...
            </mets:xmlData>
          </mets:mdWrap>
        </mets:dmdSec>
      </mets:mets>
-->
<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcp="urn:omeka-mapper:dc-pivot"
    xmlns:mets="http://www.loc.gov/METS/"
    exclude-result-prefixes="dcp">

    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>

    <xsl:template match="/">
        <mets:mets>
            <mets:metsHdr/>
            <mets:dmdSec ID="dmd1">
                <mets:mdWrap MDTYPE="DC">
                    <mets:xmlData>
                        <xsl:apply-templates select="dcp:dc-pivot/*"/>
                    </mets:xmlData>
                </mets:mdWrap>
            </mets:dmdSec>
        </mets:mets>
    </xsl:template>

    <!-- Pass DC elements through unchanged inside xmlData. -->
    <xsl:template match="dc:*">
        <xsl:copy>
            <xsl:value-of select="."/>
        </xsl:copy>
    </xsl:template>

</xsl:stylesheet>
