<?php declare(strict_types=1);

namespace MapperTest\Stdlib;

use Mapper\Stdlib\Mapper;
use MapperTest\MapperDbTestCase;

/**
 * End-to-end round-trip tests for the export pipeline.
 *
 * Item Omeka → Mapper::serialize() → XML → Mapper::convert() → properties.
 *
 * @covers \Mapper\Stdlib\Mapper::serialize
 * @covers \Mapper\Stdlib\Mapper::convert
 */
class RoundTripTest extends MapperDbTestCase
{
    protected Mapper $mapper;

    public function setUp(): void
    {
        parent::setUp();
        $this->mapper = $this->getServiceLocator()->get('Mapper\Mapper');
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    protected function dublinCorePath(): string
    {
        return dirname(dirname(dirname(__DIR__))) . '/data/mapping/dublin-core';
    }

    protected function modsPath(): string
    {
        return dirname(dirname(dirname(__DIR__))) . '/data/mapping/mods';
    }

    protected function metsPath(): string
    {
        return dirname(dirname(dirname(__DIR__))) . '/data/mapping/mets';
    }

    protected function lidoPath(): string
    {
        return dirname(dirname(dirname(__DIR__))) . '/data/mapping/lido';
    }

    protected function unimarcPath(): string
    {
        return dirname(dirname(dirname(__DIR__))) . '/data/mapping/unimarc';
    }

    /**
     * Extract the @value of each entry from a converted property array.
     */
    protected function valuesOf(array $resource, string $term): array
    {
        $entries = $resource[$term] ?? [];
        $out = [];
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $out[] = $entry['@value'] ?? $entry['@id'] ?? '';
            } else {
                $out[] = (string) $entry;
            }
        }
        return $out;
    }

    public function testRoundTripOaiDcSinglePropertyEachTerm(): void
    {
        $original = $this->createItem([
            'dcterms:title'       => 'Le titre',
            'dcterms:creator'     => 'Berthereau, Daniel',
            'dcterms:subject'     => 'Test',
            'dcterms:description' => 'Une description',
            'dcterms:publisher'   => 'Sempia',
            'dcterms:contributor' => 'Anon',
            'dcterms:date'        => '2026-05-10',
            'dcterms:type'        => 'StillImage',
            'dcterms:format'      => 'image/jpeg',
            'dcterms:identifier'  => 'urn:test:001',
            'dcterms:source'      => 'https://example.org/source',
            'dcterms:language'    => 'fra',
            'dcterms:relation'    => 'urn:related:001',
            'dcterms:coverage'    => 'France',
            'dcterms:rights'      => 'CC-BY-SA 4.0',
        ]);

        // Export.
        $this->mapper->setMapping('rt_export', file_get_contents($this->dublinCorePath() . '/omeka_to_oai_dc.xml'));
        $doc = $this->mapper->serialize($original);
        $this->assertInstanceOf(\DOMDocument::class, $doc);
        $xml = $doc->saveXML();
        $this->assertNotEmpty($xml);

        // Re-import.
        $this->mapper->setMapping('rt_import', file_get_contents($this->dublinCorePath() . '/oai_dc_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        // Compare values term by term.
        $expected = [
            'dcterms:title'       => 'Le titre',
            'dcterms:creator'     => 'Berthereau, Daniel',
            'dcterms:subject'     => 'Test',
            'dcterms:description' => 'Une description',
            'dcterms:publisher'   => 'Sempia',
            'dcterms:contributor' => 'Anon',
            'dcterms:date'        => '2026-05-10',
            'dcterms:type'        => 'StillImage',
            'dcterms:format'      => 'image/jpeg',
            'dcterms:identifier'  => 'urn:test:001',
            'dcterms:source'      => 'https://example.org/source',
            'dcterms:language'    => 'fra',
            'dcterms:relation'    => 'urn:related:001',
            'dcterms:coverage'    => 'France',
            'dcterms:rights'      => 'CC-BY-SA 4.0',
        ];
        foreach ($expected as $term => $value) {
            $this->assertSame([$value], $this->valuesOf($reimported, $term), "term=$term");
        }
    }

    public function testRoundTripOaiDcMultiValues(): void
    {
        $original = $this->createItem([
            'dcterms:title'   => ['Premier titre', 'Second titre', 'Troisième titre'],
            'dcterms:creator' => ['Alice', 'Bob'],
        ]);

        $this->mapper->setMapping('rt_export_multi', file_get_contents($this->dublinCorePath() . '/omeka_to_oai_dc.xml'));
        $doc = $this->mapper->serialize($original);
        $xml = $doc->saveXML();

        $this->mapper->setMapping('rt_import_multi', file_get_contents($this->dublinCorePath() . '/oai_dc_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $this->assertSame(
            ['Premier titre', 'Second titre', 'Troisième titre'],
            $this->valuesOf($reimported, 'dcterms:title')
        );
        $this->assertSame(['Alice', 'Bob'], $this->valuesOf($reimported, 'dcterms:creator'));
    }

    public function testRoundTripOaiDcSpecialCharacters(): void
    {
        $original = $this->createItem([
            'dcterms:title'       => 'Café & Thé < 2 €',
            'dcterms:description' => "Une description avec \"guillemets\" et apostrophes 'simples'.",
        ]);

        $this->mapper->setMapping('rt_export_special', file_get_contents($this->dublinCorePath() . '/omeka_to_oai_dc.xml'));
        $doc = $this->mapper->serialize($original);
        $xml = $doc->saveXML();

        // XML escaping must be effective.
        $this->assertStringContainsString('&amp;', $xml);
        $this->assertStringContainsString('&lt;', $xml);

        $this->mapper->setMapping('rt_import_special', file_get_contents($this->dublinCorePath() . '/oai_dc_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $this->assertSame(['Café & Thé < 2 €'], $this->valuesOf($reimported, 'dcterms:title'));
        $this->assertSame(
            ["Une description avec \"guillemets\" et apostrophes 'simples'."],
            $this->valuesOf($reimported, 'dcterms:description')
        );
    }

    public function testRoundTripOaiDcOmittedTermsStayOmitted(): void
    {
        // Only set 3 terms; the other 12 must NOT appear in the re-imported
        // resource (the export omits empty properties).
        $original = $this->createItem([
            'dcterms:title' => 'Only',
            'dcterms:date' => '2026',
            'dcterms:rights' => 'Free',
        ]);

        $this->mapper->setMapping('rt_export_omit', file_get_contents($this->dublinCorePath() . '/omeka_to_oai_dc.xml'));
        $doc = $this->mapper->serialize($original);
        $xml = $doc->saveXML();

        $this->mapper->setMapping('rt_import_omit', file_get_contents($this->dublinCorePath() . '/oai_dc_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $this->assertSame(['Only'], $this->valuesOf($reimported, 'dcterms:title'));
        $this->assertSame(['2026'], $this->valuesOf($reimported, 'dcterms:date'));
        $this->assertSame(['Free'], $this->valuesOf($reimported, 'dcterms:rights'));

        // Empty properties must be absent.
        $this->assertArrayNotHasKey('dcterms:creator', $reimported);
        $this->assertArrayNotHasKey('dcterms:subject', $reimported);
        $this->assertArrayNotHasKey('dcterms:publisher', $reimported);
    }

    // ============================================================ MODS
    // round-trip — exercises the hybrid postprocess XSL chain
    // ============================================================

    public function testRoundTripModsCoreFields(): void
    {
        $original = $this->createItem([
            'dcterms:title'       => 'Le titre MODS',
            'dcterms:creator'     => 'Berthereau, Daniel',
            'dcterms:contributor' => 'Anon',
            'dcterms:type'        => 'StillImage',
            'dcterms:publisher'   => 'Sempia',
            'dcterms:date'        => '2026',
            'dcterms:subject'     => 'Test',
            'dcterms:description' => 'Une description.',
            'dcterms:language'    => 'fra',
            'dcterms:rights'      => 'CC-BY-SA',
            'dcterms:identifier'  => 'urn:test:mods:001',
        ]);

        // Export through omeka_to_mods.xml (+ XSL postprocess).
        $this->mapper->setMapping('rt_mods_export', file_get_contents($this->modsPath() . '/omeka_to_mods.xml'));
        $doc = $this->mapper->serialize($original);
        $this->assertInstanceOf(\DOMDocument::class, $doc);
        $xml = $doc->saveXML();

        // Re-import through mods_to_omeka.xml.
        $this->mapper->setMapping('rt_mods_import', file_get_contents($this->modsPath() . '/mods_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $expected = [
            'dcterms:title'       => 'Le titre MODS',
            'dcterms:creator'     => 'Berthereau, Daniel',
            'dcterms:contributor' => 'Anon',
            'dcterms:type'        => 'StillImage',
            'dcterms:publisher'   => 'Sempia',
            'dcterms:date'        => '2026',
            'dcterms:subject'     => 'Test',
            'dcterms:description' => 'Une description.',
            'dcterms:language'    => 'fra',
            'dcterms:rights'      => 'CC-BY-SA',
            'dcterms:identifier'  => 'urn:test:mods:001',
        ];
        foreach ($expected as $term => $value) {
            $this->assertSame([$value], $this->valuesOf($reimported, $term), "term=$term");
        }
    }

    public function testRoundTripModsMultiTitlesAndCreators(): void
    {
        $original = $this->createItem([
            'dcterms:title'   => ['Titre A', 'Titre B'],
            'dcterms:creator' => ['Alice', 'Bob'],
        ]);

        $this->mapper->setMapping('rt_mods_export_multi', file_get_contents($this->modsPath() . '/omeka_to_mods.xml'));
        $doc = $this->mapper->serialize($original);
        $xml = $doc->saveXML();

        $this->mapper->setMapping('rt_mods_import_multi', file_get_contents($this->modsPath() . '/mods_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $this->assertSame(['Titre A', 'Titre B'], $this->valuesOf($reimported, 'dcterms:title'));
        $this->assertSame(['Alice', 'Bob'], $this->valuesOf($reimported, 'dcterms:creator'));
    }

    public function testRoundTripModsCreatorContributorAreDistinct(): void
    {
        // The XSL distinguishes <name> by roleTerm. The import mapping must
        // preserve that distinction through the round-trip.
        $original = $this->createItem([
            'dcterms:creator'     => 'Cathy',
            'dcterms:contributor' => 'David',
        ]);

        $this->mapper->setMapping('rt_mods_roles_export', file_get_contents($this->modsPath() . '/omeka_to_mods.xml'));
        $doc = $this->mapper->serialize($original);
        $xml = $doc->saveXML();

        $this->mapper->setMapping('rt_mods_roles_import', file_get_contents($this->modsPath() . '/mods_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $this->assertSame(['Cathy'], $this->valuesOf($reimported, 'dcterms:creator'));
        $this->assertSame(['David'], $this->valuesOf($reimported, 'dcterms:contributor'));
    }

    // ============================================================ METS
    // round-trip — DC payload wrapped in <mets:dmdSec>
    // ============================================================

    public function testRoundTripMetsCoreFields(): void
    {
        $original = $this->createItem([
            'dcterms:title'      => 'METS title',
            'dcterms:creator'    => 'Berthereau, Daniel',
            'dcterms:date'       => '2026',
            'dcterms:rights'     => 'CC-BY',
            'dcterms:identifier' => 'urn:test:mets:001',
        ]);

        $this->mapper->setMapping('rt_mets_export', file_get_contents($this->metsPath() . '/omeka_to_mets.xml'));
        $doc = $this->mapper->serialize($original);
        $this->assertInstanceOf(\DOMDocument::class, $doc);
        $xml = $doc->saveXML();

        // Basic METS structure assertions.
        $this->assertStringContainsString('<mets:mets', $xml);
        $this->assertStringContainsString('<mets:dmdSec', $xml);
        $this->assertStringContainsString('MDTYPE="DC"', $xml);

        $this->mapper->setMapping('rt_mets_import', file_get_contents($this->metsPath() . '/mets_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $expected = [
            'dcterms:title'      => 'METS title',
            'dcterms:creator'    => 'Berthereau, Daniel',
            'dcterms:date'       => '2026',
            'dcterms:rights'     => 'CC-BY',
            'dcterms:identifier' => 'urn:test:mets:001',
        ];
        foreach ($expected as $term => $value) {
            $this->assertSame([$value], $this->valuesOf($reimported, $term), "term=$term");
        }
    }

    public function testRoundTripMetsMultiValues(): void
    {
        $original = $this->createItem([
            'dcterms:title'   => ['T1', 'T2'],
            'dcterms:subject' => ['Sub1', 'Sub2', 'Sub3'],
        ]);

        $this->mapper->setMapping('rt_mets_export_multi', file_get_contents($this->metsPath() . '/omeka_to_mets.xml'));
        $doc = $this->mapper->serialize($original);
        $xml = $doc->saveXML();

        $this->mapper->setMapping('rt_mets_import_multi', file_get_contents($this->metsPath() . '/mets_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $this->assertSame(['T1', 'T2'], $this->valuesOf($reimported, 'dcterms:title'));
        $this->assertSame(['Sub1', 'Sub2', 'Sub3'], $this->valuesOf($reimported, 'dcterms:subject'));
    }

    // ============================================================ LIDO
    // round-trip — descriptive/administrative metadata
    // ============================================================

    public function testRoundTripLidoCoreFields(): void
    {
        $original = $this->createItem([
            'dcterms:title'       => 'Sculpture A',
            'dcterms:creator'     => 'Rodin, Auguste',
            'dcterms:date'        => '1880',
            'dcterms:type'        => 'Sculpture',
            'dcterms:subject'     => 'Mythologie',
            'dcterms:description' => 'Bronze patiné.',
            'dcterms:rights'      => 'Domaine public',
        ]);

        $this->mapper->setMapping('rt_lido_export', file_get_contents($this->lidoPath() . '/omeka_to_lido.xml'));
        $doc = $this->mapper->serialize($original);
        $this->assertInstanceOf(\DOMDocument::class, $doc);
        $xml = $doc->saveXML();

        // Basic LIDO structure assertions.
        $this->assertStringContainsString('<lido:lido', $xml);
        $this->assertStringContainsString('<lido:descriptiveMetadata', $xml);
        $this->assertStringContainsString('<lido:eventType>', $xml);

        $this->mapper->setMapping('rt_lido_import', file_get_contents($this->lidoPath() . '/lido_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $expected = [
            'dcterms:title'       => 'Sculpture A',
            'dcterms:creator'     => 'Rodin, Auguste',
            'dcterms:date'        => '1880',
            'dcterms:type'        => 'Sculpture',
            'dcterms:subject'     => 'Mythologie',
            'dcterms:description' => 'Bronze patiné.',
            'dcterms:rights'      => 'Domaine public',
        ];
        foreach ($expected as $term => $value) {
            $this->assertSame([$value], $this->valuesOf($reimported, $term), "term=$term");
        }

        // o:id is stamped as lidoRecID, then re-imported as dcterms:identifier.
        $identifiers = $this->valuesOf($reimported, 'dcterms:identifier');
        $this->assertCount(1, $identifiers);
        $this->assertSame((string) $original->id(), $identifiers[0]);
    }

    public function testRoundTripLidoMultiTitlesAndCreators(): void
    {
        $original = $this->createItem([
            'dcterms:title'   => ['Titre alpha', 'Titre beta'],
            'dcterms:creator' => ['Anon 1', 'Anon 2'],
        ]);

        $this->mapper->setMapping('rt_lido_export_multi', file_get_contents($this->lidoPath() . '/omeka_to_lido.xml'));
        $doc = $this->mapper->serialize($original);
        $xml = $doc->saveXML();

        $this->mapper->setMapping('rt_lido_import_multi', file_get_contents($this->lidoPath() . '/lido_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $this->assertSame(['Titre alpha', 'Titre beta'], $this->valuesOf($reimported, 'dcterms:title'));
        $this->assertSame(['Anon 1', 'Anon 2'], $this->valuesOf($reimported, 'dcterms:creator'));
    }

    // ============================================================ UNIMARC
    // round-trip — MARCXML-slim datafields/subfields
    // ============================================================

    public function testRoundTripUnimarcCoreFields(): void
    {
        $original = $this->createItem([
            'dcterms:title'       => 'Le titre UNIMARC',
            'dcterms:creator'     => 'Berthereau, Daniel',
            'dcterms:contributor' => 'Anon',
            'dcterms:publisher'   => 'Sempia',
            'dcterms:date'        => '2026',
            'dcterms:format'      => '20 cm',
            'dcterms:description' => 'Une notice.',
            'dcterms:subject'     => 'Mapping',
            'dcterms:source'      => 'https://example.org/source',
        ]);

        $this->mapper->setMapping('rt_unimarc_export', file_get_contents($this->unimarcPath() . '/omeka_to_unimarc.xml'));
        $doc = $this->mapper->serialize($original);
        $this->assertInstanceOf(\DOMDocument::class, $doc);
        $xml = $doc->saveXML();

        // Basic UNIMARC structure assertions.
        $this->assertStringContainsString('<record', $xml);
        $this->assertStringContainsString('tag="200"', $xml);
        $this->assertStringContainsString('tag="700"', $xml);

        $this->mapper->setMapping('rt_unimarc_import', file_get_contents($this->unimarcPath() . '/unimarc_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $expected = [
            'dcterms:title'       => 'Le titre UNIMARC',
            'dcterms:creator'     => 'Berthereau, Daniel',
            'dcterms:contributor' => 'Anon',
            'dcterms:publisher'   => 'Sempia',
            'dcterms:date'        => '2026',
            'dcterms:format'      => '20 cm',
            'dcterms:description' => 'Une notice.',
            'dcterms:subject'     => 'Mapping',
            'dcterms:source'      => 'https://example.org/source',
        ];
        foreach ($expected as $term => $value) {
            $this->assertSame([$value], $this->valuesOf($reimported, $term), "term=$term");
        }

        // o:id stamped as 001 controlfield, re-imported as identifier.
        $identifiers = $this->valuesOf($reimported, 'dcterms:identifier');
        $this->assertCount(1, $identifiers);
        $this->assertSame((string) $original->id(), $identifiers[0]);
    }

    public function testRoundTripUnimarcMultiSubjectsAndCreators(): void
    {
        $original = $this->createItem([
            'dcterms:title'   => 'Une œuvre',
            'dcterms:creator' => ['Auteur principal', 'Co-auteur'],
            'dcterms:subject' => ['Histoire', 'Art', 'Patrimoine'],
        ]);

        $this->mapper->setMapping('rt_unimarc_export_multi', file_get_contents($this->unimarcPath() . '/omeka_to_unimarc.xml'));
        $doc = $this->mapper->serialize($original);
        $xml = $doc->saveXML();

        $this->mapper->setMapping('rt_unimarc_import_multi', file_get_contents($this->unimarcPath() . '/unimarc_to_omeka.xml'));
        $reimported = $this->mapper->convert(new \SimpleXMLElement($xml));

        $this->assertSame(['Auteur principal', 'Co-auteur'], $this->valuesOf($reimported, 'dcterms:creator'));
        $this->assertSame(['Histoire', 'Art', 'Patrimoine'], $this->valuesOf($reimported, 'dcterms:subject'));
    }
}
