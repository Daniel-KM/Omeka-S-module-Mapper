<?xml version="1.0" encoding="UTF-8"?>
<!--
    Postprocess XSL : transforme le pivot DC produit par
    omeka_to_lido.xml en un document LIDO simple (un seul lido:lido).

    Sortie :
      <lido:lido xmlns:lido="http://www.lido-schema.org">
        <lido:lidoRecID>...</lido:lidoRecID>
        <lido:descriptiveMetadata xml:lang="und">
          <lido:objectClassificationWrap>
            <lido:objectWorkTypeWrap>
              <lido:objectWorkType>
                <lido:term>...</lido:term>
              </lido:objectWorkType>
            </lido:objectWorkTypeWrap>
          </lido:objectClassificationWrap>
          <lido:objectIdentificationWrap>
            <lido:titleWrap>
              <lido:titleSet><lido:appellationValue>...</lido:appellationValue></lido:titleSet>
            </lido:titleWrap>
            <lido:objectDescriptionWrap>
              <lido:objectDescriptionSet>
                <lido:descriptiveNoteValue>...</lido:descriptiveNoteValue>
              </lido:objectDescriptionSet>
            </lido:objectDescriptionWrap>
          </lido:objectIdentificationWrap>
          <lido:eventWrap>
            <lido:eventSet>
              <lido:event>
                <lido:eventType><lido:term>creation</lido:term></lido:eventType>
                <lido:eventActor><lido:actorInRole>
                  <lido:actor><lido:nameActorSet>
                    <lido:appellationValue>...</lido:appellationValue>
                  </lido:nameActorSet></lido:actor>
                </lido:actorInRole></lido:eventActor>
                <lido:eventDate><lido:displayDate>...</lido:displayDate></lido:eventDate>
              </lido:event>
            </lido:eventSet>
          </lido:eventWrap>
        </lido:descriptiveMetadata>
        <lido:administrativeMetadata xml:lang="und">
          <lido:rightsWorkWrap>
            <lido:rightsWorkSet>
              <lido:creditLine>...</lido:creditLine>
            </lido:rightsWorkSet>
          </lido:rightsWorkWrap>
        </lido:administrativeMetadata>
      </lido:lido>
-->
<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcp="urn:omeka-mapper:dc-pivot"
    xmlns:lido="http://www.lido-schema.org"
    exclude-result-prefixes="dc dcp">

    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>

    <xsl:template match="/">
        <lido:lido>
            <xsl:if test="dcp:dc-pivot/dc:identifier">
                <lido:lidoRecID>
                    <xsl:value-of select="dcp:dc-pivot/dc:identifier[1]"/>
                </lido:lidoRecID>
            </xsl:if>

            <lido:descriptiveMetadata xml:lang="und">

                <xsl:if test="dcp:dc-pivot/dc:type">
                    <lido:objectClassificationWrap>
                        <lido:objectWorkTypeWrap>
                            <xsl:for-each select="dcp:dc-pivot/dc:type">
                                <lido:objectWorkType>
                                    <lido:term><xsl:value-of select="."/></lido:term>
                                </lido:objectWorkType>
                            </xsl:for-each>
                        </lido:objectWorkTypeWrap>
                    </lido:objectClassificationWrap>
                </xsl:if>

                <xsl:if test="dcp:dc-pivot/dc:title or dcp:dc-pivot/dc:description or dcp:dc-pivot/dc:subject">
                    <lido:objectIdentificationWrap>
                        <xsl:if test="dcp:dc-pivot/dc:title">
                            <lido:titleWrap>
                                <xsl:for-each select="dcp:dc-pivot/dc:title">
                                    <lido:titleSet>
                                        <lido:appellationValue><xsl:value-of select="."/></lido:appellationValue>
                                    </lido:titleSet>
                                </xsl:for-each>
                            </lido:titleWrap>
                        </xsl:if>
                        <xsl:if test="dcp:dc-pivot/dc:description">
                            <lido:objectDescriptionWrap>
                                <xsl:for-each select="dcp:dc-pivot/dc:description">
                                    <lido:objectDescriptionSet>
                                        <lido:descriptiveNoteValue><xsl:value-of select="."/></lido:descriptiveNoteValue>
                                    </lido:objectDescriptionSet>
                                </xsl:for-each>
                            </lido:objectDescriptionWrap>
                        </xsl:if>
                        <xsl:if test="dcp:dc-pivot/dc:subject">
                            <lido:subjectWrap>
                                <xsl:for-each select="dcp:dc-pivot/dc:subject">
                                    <lido:subjectSet>
                                        <lido:displaySubject><xsl:value-of select="."/></lido:displaySubject>
                                    </lido:subjectSet>
                                </xsl:for-each>
                            </lido:subjectWrap>
                        </xsl:if>
                    </lido:objectIdentificationWrap>
                </xsl:if>

                <xsl:if test="dcp:dc-pivot/dc:creator or dcp:dc-pivot/dc:date">
                    <lido:eventWrap>
                        <lido:eventSet>
                            <lido:event>
                                <lido:eventType><lido:term>creation</lido:term></lido:eventType>
                                <xsl:for-each select="dcp:dc-pivot/dc:creator">
                                    <lido:eventActor>
                                        <lido:actorInRole>
                                            <lido:actor>
                                                <lido:nameActorSet>
                                                    <lido:appellationValue><xsl:value-of select="."/></lido:appellationValue>
                                                </lido:nameActorSet>
                                            </lido:actor>
                                        </lido:actorInRole>
                                    </lido:eventActor>
                                </xsl:for-each>
                                <xsl:for-each select="dcp:dc-pivot/dc:date">
                                    <lido:eventDate>
                                        <lido:displayDate><xsl:value-of select="."/></lido:displayDate>
                                    </lido:eventDate>
                                </xsl:for-each>
                            </lido:event>
                        </lido:eventSet>
                    </lido:eventWrap>
                </xsl:if>

            </lido:descriptiveMetadata>

            <xsl:if test="dcp:dc-pivot/dc:rights">
                <lido:administrativeMetadata xml:lang="und">
                    <lido:rightsWorkWrap>
                        <xsl:for-each select="dcp:dc-pivot/dc:rights">
                            <lido:rightsWorkSet>
                                <lido:creditLine><xsl:value-of select="."/></lido:creditLine>
                            </lido:rightsWorkSet>
                        </xsl:for-each>
                    </lido:rightsWorkWrap>
                </lido:administrativeMetadata>
            </xsl:if>

        </lido:lido>
    </xsl:template>

</xsl:stylesheet>
