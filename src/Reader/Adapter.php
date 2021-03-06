<?php

namespace PabloVeintimilla\FacturaEC\Reader;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use PabloVeintimilla\FacturaEC\Model\Enum\VoucherType;

/**
 * Adapt xml schema from SRI to objects.
 *
 * @author Pablo Veintimilla Vargas <pabloveintimilla@gmail.com>
 */
class Adapter
{
    /**
     * @var string XML data
     */
    private $data;

    /**
     *  Data load in \DOMDocument.
     *
     * @var \DOMDocument
     */
    private $domDocument;

    /**
     * @var string Full path to xsl resources
     */
    private $xslPath;

    /**
     * Flag voucer signed.
     *
     * @var bool
     */
    private $voucherType = null;

    /**
     * @param string $data XML data
     */
    public function __construct($data)
    {
        $this->data = $data;
        $this->domDocument = $this->extractData();
        $this->voucherType = $this->detectVoucherType();

        $this->xslPath = dirname(dirname(__DIR__))
            .DIRECTORY_SEPARATOR.'resources'
            .DIRECTORY_SEPARATOR.'schemas'
            .DIRECTORY_SEPARATOR.'xsl'
            .DIRECTORY_SEPARATOR;
    }

    /**
     * Transform from xml from SRI To FacturaEC.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function tranformIn()
    {
        return $this
                ->getXsl()
                ->transformToXML($this->domDocument);
    }

    /**
     * Get Xsl processor.
     *
     * @return \XSLTProcessor
     */
    private function getXsl()
    {
        $filename = strtolower($this->voucherType);
        $filepath = $this->xslPath.$filename.'.xsl';

        if (!file_exists($filepath)) {
            throw new FileNotFoundException('XSL file not found');
        }

        // Load XSL file
        $xsl = new \DOMDocument();
        $xsl->load($filepath);

        // Configure the transformer and attach the xsl rules
        $processor = new \XSLTProcessor();
        $processor->importStyleSheet($xsl);

        return $processor;
    }

    /**
     * Extract voucher data without sign.
     * 
     * @return \DOMDocument
     */
    private function extractData()
    {
        $xml = new \DOMDocument();
        $xml->loadXML($this->data);
        $root = $xml->documentElement->nodeName;

        //Check if voucher is signed
        if ($root == !'autorizacion') {
            return $xml;
        }

        //Get voucher
        $voucher = $xml->getElementsByTagName('comprobante');

        foreach ($voucher as $item) {
            foreach ($item->childNodes as $child) {
                if (XML_CDATA_SECTION_NODE == $child->nodeType) {
                    $item = $child->textContent;
                }
            }
        }

        $document = new \DOMDocument();
        $document->loadXML($item);

        return $document;
    }

    /**
     * Detect voucher type by xml.
     *
     * @return string
     */
    private function detectVoucherType()
    {
        $voucherType = '';

        $xpath = new \DOMXPath($this->domDocument);
        $codeDocument = $xpath->query('//infoTributaria/codDoc')
            ->item(0);

        if (!$codeDocument) {
            return $voucherType;
        }

        $type = VoucherType::accepts($codeDocument->textContent)
            ? $codeDocument->textContent
            : false;

        if (!$type) {
            return $voucherType;
        }

        return VoucherType::getLabel($type);
    }

    /**
     * Get voucher type detected of xml.
     *
     * @return string
     */
    public function getVoucherType()
    {
        return $this->voucherType;
    }
}
