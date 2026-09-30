<?php

namespace MapSVG;

/**
 * Row model for the import_settings table (Google Sheets / CSV import config and preflight).
 */
class ImportSetting extends Model
{
    public $schema_id;
    public $schema_name;
    public $gsSync;
    public $gsAutoRefetch;
    public $gsSyncMode;
    public $gsCsvUrl;
    public $gsCsvHash;
    public $gsRefetchInterval;
    public $gsAutoId;
    public $gsIdFieldName;
    public $gsSheetName;
    public $gsGeocode;
    public $gsGeocodeConvertLatLngToAddress;
    public $gsGeocodeConvertAddressToLatLng;
    public $gsPaidGeocoding;
    public $gsAppScriptUrl;
    public $gsSecret;
    public $gsImportFinishedAt;
    public $gsImportStartedAt;
    public $gsImportLastUpdatedAt;
    public $gsImportEstimatedSeconds;
    public $gsImportSource;
    public $gsImportSourceValid;
    public $gsImportSkipFields;
    public $preflightToken;
    public $preflightStatus;
    public $preflightExpiresAt;
    public $preflightFilePath;
    public $preflightFileHash;
    public $preflightMeta;

    public function __construct($data = null)
    {
        parent::__construct($data !== null ? (array) $data : []);
    }

    public function update($params)
    {
        foreach ((array) $params as $paramName => $value) {
            $methodName = 'set' . ucfirst((string) $paramName);
            if (method_exists($this, $methodName)) {
                $this->{$methodName}($value);
            } elseif (property_exists($this, $paramName)) {
                $this->{$paramName} = $value;
            }
        }
        return $this;
    }
}
