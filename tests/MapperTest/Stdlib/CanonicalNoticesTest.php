<?php declare(strict_types=1);

namespace MapperTest\Stdlib;

use Mapper\Stdlib\Mapper;
use MapperTest\MapperDbTestCase;

/**
 * Import tests against real, canonical notices of each standard.
 *
 * Unlike RoundTripTest (which re-imports the module's own export), these
 * fixtures are authentic records taken from the standards' reference
 * documentation. They guard the import mappings against real-world structure:
 * each test asserts that parsing does not fail and that the canonical fields
 * the KISS mapping supports are extracted. Known gaps (fields a real notice
 * carries but the mapping does not extract) are pinned with explicit
 * assertions, so improving a mapping flags the test.
 *
 * @covers \Mapper\Stdlib\Mapper::convert
 */
class CanonicalNoticesTest extends MapperDbTestCase
{
    protected Mapper $mapper;

    public function setUp(): void
    {
        parent::setUp();
        $this->mapper = $this->getServiceLocator()->get('Mapper\Mapper');
    }

    protected function mappingPath(string $standard): string
    {
        return dirname(dirname(dirname(__DIR__))) . '/data/mapping/' . $standard;
    }

    protected function fixture(string $relative): string
    {
        return file_get_contents(dirname(dirname(dirname(__DIR__))) . '/tests/fixtures/' . $relative);
    }

    /**
     * Import a fixture through the given import mapping.
     *
     * The mapping name must be unique per call: the mapper caches parsed
     * mappings by name, so reusing a name would silently apply a stale mapping.
     */
    protected function import(string $mapping, string $fixtureRelative): array
    {
        $this->mapper->setMapping('canonical_' . md5($mapping), file_get_contents($mapping));
        return $this->mapper->convert(new \SimpleXMLElement($this->fixture($fixtureRelative)));
    }

    /**
     * Extract the @value (or @id) of each entry of a converted property.
     */
    protected function valuesOf(array $resource, string $term): array
    {
        $out = [];
        foreach ($resource[$term] ?? [] as $entry) {
            $out[] = is_array($entry) ? ($entry['@value'] ?? $entry['@id'] ?? '') : (string) $entry;
        }
        return $out;
    }

    public function testParsesOpenArchivesOaiDcGetRecord(): void
    {
        $result = $this->import(
            $this->mappingPath('dublin-core') . '/oai_dc_to_omeka.xml',
            'oai_dc/openarchives-getrecord.xml'
        );

        $this->assertNotEmpty($result);
        $this->assertSame(
            ['Using Structural Metadata to Localize Experience of Digital Content'],
            $this->valuesOf($result, 'dcterms:title')
        );
        $this->assertSame(['Dushay, Naomi'], $this->valuesOf($result, 'dcterms:creator'));
        $this->assertSame(['Digital Libraries'], $this->valuesOf($result, 'dcterms:subject'));
        $this->assertSame(['2001-12-14'], $this->valuesOf($result, 'dcterms:date'));
        $this->assertSame(['e-print'], $this->valuesOf($result, 'dcterms:type'));
        $this->assertSame(['http://arXiv.org/abs/cs/0112017'], $this->valuesOf($result, 'dcterms:identifier'));
    }

    public function testParsesLocModsSoundAndFury(): void
    {
        $result = $this->import(
            $this->mappingPath('mods') . '/mods_to_omeka.xml',
            'mods/loc-sound-and-fury.xml'
        );

        $this->assertNotEmpty($result);
        $this->assertSame(['Sound and fury'], $this->valuesOf($result, 'dcterms:title'));
        $this->assertSame(['text'], $this->valuesOf($result, 'dcterms:type'));
        $this->assertSame(['Cornell University Press'], $this->valuesOf($result, 'dcterms:publisher'));
        $this->assertSame(['c1999'], $this->valuesOf($result, 'dcterms:date'));
        $this->assertSame(['Mass media', 'Political aspects'], $this->valuesOf($result, 'dcterms:subject'));
        $this->assertSame(['eng'], $this->valuesOf($result, 'dcterms:language'));
        $this->assertSame(['0801486394'], $this->valuesOf($result, 'dcterms:identifier'));

        // Known gap: marcrelator role code "aut" is not mapped to creator (the
        // mapping expects roleTerm='creator'). Pinned to flag any improvement.
        $this->assertArrayNotHasKey('dcterms:creator', $result);
    }

    public function testParsesLocMetsSimpleDc(): void
    {
        $result = $this->import(
            $this->mappingPath('mets') . '/mets_to_omeka.xml',
            'mets/loc-simple-dc.xml'
        );

        $this->assertNotEmpty($result);
        $this->assertSame(['The Adventures of Tom Sawyer'], $this->valuesOf($result, 'dcterms:title'));
        $this->assertSame(['Twain, Mark'], $this->valuesOf($result, 'dcterms:creator'));
        $this->assertSame(['1876'], $this->valuesOf($result, 'dcterms:date'));
        $this->assertSame(['text'], $this->valuesOf($result, 'dcterms:type'));
        $this->assertSame(['loc-simple-001'], $this->valuesOf($result, 'dcterms:identifier'));
        $this->assertSame(['Public domain'], $this->valuesOf($result, 'dcterms:rights'));
    }

    public function testParsesBnfUnimarcMonograph(): void
    {
        $result = $this->import(
            $this->mappingPath('unimarc') . '/unimarc_to_omeka.xml',
            'unimarc/bnf-monograph.xml'
        );

        $this->assertNotEmpty($result);
        $this->assertSame(['FRBNF37745902'], $this->valuesOf($result, 'dcterms:identifier'));
        $this->assertSame(['Les misérables'], $this->valuesOf($result, 'dcterms:title'));
        $this->assertSame(['Gallimard'], $this->valuesOf($result, 'dcterms:publisher'));
        $this->assertSame(['1951'], $this->valuesOf($result, 'dcterms:date'));
        $this->assertSame(['1781 p.'], $this->valuesOf($result, 'dcterms:format'));
        $this->assertSame(['Édition de référence'], $this->valuesOf($result, 'dcterms:description'));
        $this->assertSame(['Roman français'], $this->valuesOf($result, 'dcterms:subject'));
        $this->assertSame(['Hugo'], $this->valuesOf($result, 'dcterms:creator'));
        $this->assertSame(['Rosa'], $this->valuesOf($result, 'dcterms:contributor'));
        $this->assertSame(
            ['https://gallica.bnf.fr/ark:/12148/bpt6k375010'],
            $this->valuesOf($result, 'dcterms:source')
        );
    }
}
