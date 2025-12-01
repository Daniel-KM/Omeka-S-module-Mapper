<?xml version="1.0" encoding="UTF-8"?>
<!--
    Description : Pré-traite un fichier LIDO/LIDO-MC pour le convertir en liste de ressources exploitables par l'alignement lido_mc_to_omeka.xml.

    Ce fichier XSL effectue les opérations suivantes :
    - Gère les fichiers multi-enregistrements (lidoWrap) en les séparant
    - Normalise les namespaces
    - Ajoute des attributs techniques pour faciliter le mapping

    LIDO-MC est le profil d'application français du standard international LIDO,
    adopté par le Ministère de la Culture pour harmoniser les flux de données culturelles.

    Ontologies et vocabulaires utilisés par LIDO / LIDO-MC :
    =========================================================
    - dcterms  : Dublin Core Terms (http://purl.org/dc/terms/)
    - skos     : SKOS (http://www.w3.org/2004/02/skos/core#)
    - rdf      : RDF (http://www.w3.org/1999/02/22-rdf-syntax-ns#)
    - rdfs     : RDF Schema (http://www.w3.org/2000/01/rdf-schema#)
    - owl      : OWL (http://www.w3.org/2002/07/owl#)
    - foaf     : FOAF (http://xmlns.com/foaf/0.1/)
    - crm      : CIDOC-CRM (http://www.cidoc-crm.org/cidoc-crm/)
    - gml      : GML (http://www.opengis.net/gml)
    - edm      : Europeana Data Model (http://www.europeana.eu/schemas/edm/)

    Thésauri référencés :
    - Getty AAT, ULAN, TGN
    - Wikidata, VIAF, GND
    - IdRef/Rameau (France)
    - LIDO Terminology (http://terminology.lido-schema.org)

    @link https://lido-schema.org/
    @link https://www.culture.gouv.fr/thematiques/innovation-numerique/faciliter-l-acces-aux-donnees-et-aux-contenus-culturels/partager-et-valoriser-les-donnees-et-les-contenus-culturels/partager-facilement-les-donnees-culturelles-avec-le-profil-d-application-lido-mc

    Paramètres :
    ============
    - basepath : URL ou chemin de base pour les fichiers images. Par défaut `__dirpath__` utilise le dossier du fichier XML.
    - resource_is_file : "0" (défaut) ou "1" pour indiquer si les linkResource sont des fichiers locaux.
    - include_private : "0" (défaut) ou "1" pour inclure les métadonnées administratives détaillées.

    @copyright Daniel Berthereau, 2026
    @license CeCILL 2.1 https://cecill.info/licences/Licence_CeCILL_V2.1-fr.txt
-->

<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:exsl="http://exslt.org/common"

    xmlns:lido="http://www.lido-schema.org"
    xmlns:gml="http://www.opengis.net/gml"
    xmlns:skos="http://www.w3.org/2004/02/skos/core#"
    xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
    xmlns:owl="http://www.w3.org/2002/07/owl#"

    xmlns:o="http://omeka.org/s/vocabs/o#"
    xmlns:dcterms="http://purl.org/dc/terms/"
    xmlns:bibo="http://purl.org/ontology/bibo/"
    xmlns:foaf="http://xmlns.com/foaf/0.1/"

    exclude-result-prefixes="xsl exsl"
    extension-element-prefixes="exsl"
    >

    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>

    <xsl:strip-space elements="*"/>

    <!-- Paramètres -->

    <!-- URL ou chemin de base pour les fichiers, avec le "/" final. La valeur spéciale `__dirpath__` insère le dossier du fichier XML. -->
    <xsl:param name="basepath">__dirpath__</xsl:param>

    <!-- URL ou chemin du fichier XML, automatiquement passé. -->
    <xsl:param name="filepath"></xsl:param>

    <!-- URL ou chemin du dossier du fichier XML, automatiquement passé. -->
    <xsl:param name="dirpath"></xsl:param>

    <!-- Indique si les linkResource sont des fichiers locaux (0 / 1). -->
    <xsl:param name="resource_is_file">0</xsl:param>

    <!-- Inclure les métadonnées administratives détaillées (0 / 1). -->
    <xsl:param name="include_private">0</xsl:param>

    <!-- Templates -->

    <!-- Point d'entrée : gère lidoWrap (multi-enregistrements) ou lido (mono-enregistrement) -->
    <xsl:template match="/">
        <resources>
            <xsl:choose>
                <!-- Fichier multi-enregistrements avec namespace -->
                <xsl:when test="lido:lidoWrap">
                    <xsl:apply-templates select="lido:lidoWrap/lido:lido"/>
                </xsl:when>
                <!-- Fichier multi-enregistrements sans namespace -->
                <xsl:when test="lidoWrap">
                    <xsl:apply-templates select="lidoWrap/lido"/>
                </xsl:when>
                <!-- Fichier mono-enregistrement avec namespace -->
                <xsl:when test="lido:lido">
                    <xsl:apply-templates select="lido:lido"/>
                </xsl:when>
                <!-- Fichier mono-enregistrement sans namespace -->
                <xsl:when test="lido">
                    <xsl:apply-templates select="lido"/>
                </xsl:when>
            </xsl:choose>
        </resources>
    </xsl:template>

    <!-- Traitement de chaque enregistrement LIDO -->
    <xsl:template match="lido:lido | lido">
        <resource wrapper="1" type="lido">
            <!-- Copie de l'enregistrement avec attributs techniques ajoutés -->
            <lido>
                <!-- Identifiant unique pour les relations -->
                <xsl:attribute name="_id">
                    <xsl:call-template name="get_record_id"/>
                </xsl:attribute>

                <!-- Copie des attributs existants -->
                <xsl:apply-templates select="@*"/>

                <!-- Copie des éléments enfants -->
                <xsl:apply-templates select="node()"/>
            </lido>
        </resource>
    </xsl:template>

    <!-- Génération de l'identifiant unique de l'enregistrement -->
    <xsl:template name="get_record_id">
        <xsl:choose>
            <!-- lidoRecID est obligatoire -->
            <xsl:when test="lido:lidoRecID | lidoRecID">
                <xsl:value-of select="lido:lidoRecID | lidoRecID"/>
            </xsl:when>
            <!-- Fallback sur recordID administratif -->
            <xsl:when test=".//lido:recordID | .//recordID">
                <xsl:value-of select="(.//lido:recordID | .//recordID)[1]"/>
            </xsl:when>
            <!-- Fallback sur identifiant généré -->
            <xsl:otherwise>
                <xsl:value-of select="generate-id()"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Correction du chemin des fichiers/images -->
    <xsl:template match="lido:linkResource | linkResource">
        <xsl:copy>
            <xsl:apply-templates select="@*"/>
            <!-- Attribut pour indiquer si c'est un fichier -->
            <xsl:attribute name="_file">
                <xsl:value-of select="$resource_is_file"/>
            </xsl:attribute>
            <!-- Contenu avec chemin corrigé -->
            <xsl:choose>
                <!-- URL absolue : conserver tel quel -->
                <xsl:when test="starts-with(., 'http://') or starts-with(., 'https://')">
                    <xsl:value-of select="."/>
                </xsl:when>
                <!-- Chemin relatif avec basepath automatique -->
                <xsl:when test="$basepath = '__dirpath__' and $dirpath != ''">
                    <xsl:value-of select="concat($dirpath, '/', .)"/>
                </xsl:when>
                <!-- Chemin relatif avec basepath personnalisé -->
                <xsl:when test="$basepath != '' and $basepath != '__dirpath__'">
                    <xsl:value-of select="concat($basepath, .)"/>
                </xsl:when>
                <!-- Pas de modification -->
                <xsl:otherwise>
                    <xsl:value-of select="."/>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:copy>
    </xsl:template>

    <!-- Normalisation des dates ISO pour faciliter le parsing -->
    <xsl:template match="lido:earliestDate | earliestDate | lido:latestDate | latestDate">
        <xsl:copy>
            <xsl:apply-templates select="@*"/>
            <!-- Attribut pour le type de date -->
            <xsl:attribute name="_date_type">
                <xsl:choose>
                    <xsl:when test="local-name() = 'earliestDate'">start</xsl:when>
                    <xsl:otherwise>end</xsl:otherwise>
                </xsl:choose>
            </xsl:attribute>
            <xsl:apply-templates select="node()"/>
        </xsl:copy>
    </xsl:template>

    <!-- Normalisation des acteurs avec URI d'autorité -->
    <xsl:template match="lido:actor | actor">
        <xsl:copy>
            <xsl:apply-templates select="@*"/>
            <!-- Extraction de l'URI d'autorité principale si présent -->
            <xsl:if test="lido:actorID[contains(., 'viaf.org')] | actorID[contains(., 'viaf.org')]">
                <xsl:attribute name="_viaf">
                    <xsl:value-of select="(lido:actorID[contains(., 'viaf.org')] | actorID[contains(., 'viaf.org')])[1]"/>
                </xsl:attribute>
            </xsl:if>
            <xsl:if test="lido:actorID[contains(., 'wikidata.org')] | actorID[contains(., 'wikidata.org')]">
                <xsl:attribute name="_wikidata">
                    <xsl:value-of select="(lido:actorID[contains(., 'wikidata.org')] | actorID[contains(., 'wikidata.org')])[1]"/>
                </xsl:attribute>
            </xsl:if>
            <xsl:if test="lido:actorID[contains(., 'idref.fr')] | actorID[contains(., 'idref.fr')]">
                <xsl:attribute name="_idref">
                    <xsl:value-of select="(lido:actorID[contains(., 'idref.fr')] | actorID[contains(., 'idref.fr')])[1]"/>
                </xsl:attribute>
            </xsl:if>
            <xsl:apply-templates select="node()"/>
        </xsl:copy>
    </xsl:template>

    <!-- Normalisation des lieux avec URI d'autorité -->
    <xsl:template match="lido:place | place">
        <xsl:copy>
            <xsl:apply-templates select="@*"/>
            <!-- Extraction de l'URI d'autorité principale si présent -->
            <xsl:if test="lido:placeID[contains(., 'getty.edu/tgn')] | placeID[contains(., 'getty.edu/tgn')]">
                <xsl:attribute name="_tgn">
                    <xsl:value-of select="(lido:placeID[contains(., 'getty.edu/tgn')] | placeID[contains(., 'getty.edu/tgn')])[1]"/>
                </xsl:attribute>
            </xsl:if>
            <xsl:if test="lido:placeID[contains(., 'wikidata.org')] | placeID[contains(., 'wikidata.org')]">
                <xsl:attribute name="_wikidata">
                    <xsl:value-of select="(lido:placeID[contains(., 'wikidata.org')] | placeID[contains(., 'wikidata.org')])[1]"/>
                </xsl:attribute>
            </xsl:if>
            <!-- Extraction des coordonnées GML -->
            <xsl:if test=".//gml:pos">
                <xsl:attribute name="_coordinates">
                    <xsl:value-of select=".//gml:pos"/>
                </xsl:attribute>
            </xsl:if>
            <xsl:apply-templates select="node()"/>
        </xsl:copy>
    </xsl:template>

    <!-- Normalisation des concepts (types, sujets) avec URI -->
    <xsl:template match="lido:objectWorkType | objectWorkType | lido:classification | classification | lido:subjectConcept | subjectConcept">
        <xsl:copy>
            <xsl:apply-templates select="@*"/>
            <!-- Extraction de l'URI AAT si présent -->
            <xsl:if test="lido:conceptID[contains(., 'getty.edu/aat')] | conceptID[contains(., 'getty.edu/aat')]">
                <xsl:attribute name="_aat">
                    <xsl:value-of select="(lido:conceptID[contains(., 'getty.edu/aat')] | conceptID[contains(., 'getty.edu/aat')])[1]"/>
                </xsl:attribute>
            </xsl:if>
            <!-- Extraction de l'URI Wikidata si présent -->
            <xsl:if test="lido:conceptID[contains(., 'wikidata.org')] | conceptID[contains(., 'wikidata.org')]">
                <xsl:attribute name="_wikidata">
                    <xsl:value-of select="(lido:conceptID[contains(., 'wikidata.org')] | conceptID[contains(., 'wikidata.org')])[1]"/>
                </xsl:attribute>
            </xsl:if>
            <xsl:apply-templates select="node()"/>
        </xsl:copy>
    </xsl:template>

    <!-- Marquage des événements par type -->
    <xsl:template match="lido:event | event">
        <xsl:copy>
            <xsl:apply-templates select="@*"/>
            <!-- Attribut pour le type d'événement normalisé -->
            <xsl:attribute name="_event_type">
                <xsl:variable name="eventTypeTerm" select="normalize-space((lido:eventType/lido:term | eventType/term)[1])"/>
                <xsl:choose>
                    <xsl:when test="contains($eventTypeTerm, 'Production') or contains($eventTypeTerm, 'production') or contains($eventTypeTerm, 'Creation') or contains($eventTypeTerm, 'création') or contains($eventTypeTerm, 'Création')">production</xsl:when>
                    <xsl:when test="contains($eventTypeTerm, 'Finding') or contains($eventTypeTerm, 'découverte') or contains($eventTypeTerm, 'Découverte') or contains($eventTypeTerm, 'Excavation') or contains($eventTypeTerm, 'fouille')">finding</xsl:when>
                    <xsl:when test="contains($eventTypeTerm, 'Acquisition') or contains($eventTypeTerm, 'acquisition')">acquisition</xsl:when>
                    <xsl:when test="contains($eventTypeTerm, 'Exhibition') or contains($eventTypeTerm, 'exposition') or contains($eventTypeTerm, 'Exposition')">exhibition</xsl:when>
                    <xsl:when test="contains($eventTypeTerm, 'Restoration') or contains($eventTypeTerm, 'restauration') or contains($eventTypeTerm, 'Restauration')">restoration</xsl:when>
                    <xsl:when test="contains($eventTypeTerm, 'Use') or contains($eventTypeTerm, 'utilisation') or contains($eventTypeTerm, 'Utilisation')">use</xsl:when>
                    <xsl:when test="contains($eventTypeTerm, 'Publication') or contains($eventTypeTerm, 'publication')">publication</xsl:when>
                    <xsl:otherwise>other</xsl:otherwise>
                </xsl:choose>
            </xsl:attribute>
            <xsl:apply-templates select="node()"/>
        </xsl:copy>
    </xsl:template>

    <!-- Template d'identité : copie tout le reste tel quel -->
    <xsl:template match="@*|node()">
        <xsl:copy>
            <xsl:apply-templates select="@*|node()"/>
        </xsl:copy>
    </xsl:template>

</xsl:stylesheet>
