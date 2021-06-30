<?php

namespace Symbiote\MultiValueField\Fields;

use Symbiote\MultiValueField\ORM\FieldType\MultiValueField;

use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Controller;
use SilverStripe\View\HTML;
use SilverStripe\Forms\FormField;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Convert;

/**
 * Lightweight gridfield for editing multiple psuedo-records.
 * 
 * Define fields in the 'record' as follows:
 * 
 * $recordSpec = [
 *     'Icon' => [
 *         'Type' => 'MultiValueDropdownField',
 *         'Options' => ['Standard', 'Video', 'Help']
 *     ],
 *     'Content'  =>  'MultiValueTextField',
 * ];
 * 
 */
class MultiRecordField extends FormField
{
    const KEY_SEP = '__';

    protected $tag = 'input';

    protected $recordSpec;

    public function __construct($name, $title = null, $recordSpec = ['Content' => 'Text'], $value = null)
    {
        parent::__construct($name, ($title === null) ? $name : $title, $value);
        $this->recordSpec = $recordSpec;
    }

    public function Field($properties = [])
    {
        if (Controller::curr() instanceof ContentController) {
            Requirements::javascript('silverstripe/admin: thirdparty/jquery/jquery.js');
        }
        Requirements::javascript('symbiote/silverstripe-multivaluefield: client/javascript/multivaluefield.js');
        Requirements::css('symbiote/silverstripe-multivaluefield: client/css/multivaluefield.css');

        // add a row of fields for each 'record' in the saved json 
        $fields = [];
        if ($this->value) {
            foreach ($this->value as $recordID => $record) {
                $fields[] = $this->renderGridRow($recordID, $record);
            }
        }

        // add an empty row of fields (for adding new 'records')
        if (!$this->readonly) {
            // assume next id is numeric: index+1
            $recordID = count($fields);

            // new empty record
            $record = [];
            foreach ($this->recordSpec as $fieldName => $fieldSpec) {
                $record[$fieldName] = null;
            }

            $fields[] = $this->renderGridRow($recordID, $record);
        }

        // render
        if (count($fields)) {
            return '<ul id="'.$this->id().'" class="multivaluefieldlist '.$this->extraClass().'"><li>'.implode('</li><li>',
                    $fields).'</li></ul>';
        } else {
            return '<div id="'.$this->id().'" class="multivaluefieldlist '.$this->extraClass().'"></div>';
        }
    }

    /**
     * Render a row of fields defined by $this->recordSpec
     */
    public function renderGridRow($recordID, $record)
    {
        $fields = [];
        foreach ($this->recordSpec as $fieldName => $fieldSpec) {
            $value = array_key_exists($fieldName, $record)? $record[$fieldName] : null;

            $type = is_array($fieldSpec)? $fieldSpec['Type'] : $fieldSpec;

            // TODO: refactor single value fields to obtain rendered html from them,
            // rather than re-implementing their 'create' methods here
            if ($type=="MultiValueDropdownField") {
                $fields[] = $this->createMultiValueDropdownField($recordID, $fieldName, $value);
            } else {
                $fields[] = $this->createMultiValueTextField($recordID, $fieldName, $value);
            }
        }

        return join(" ", $fields);
    }

    /**
     * Render a single text field
     */
    public function createMultiValueTextField($recordID, $fieldName, $value = null)
    {
        $attrs = [
            'type' => 'text',
            'class' => 'text mventryfield mvtextfield '.($this->extraClass() ? $this->extraClass() : ''),
            // front-end javascript assumes recordID is last when adding new rows
            'id' => $this->id() .  MultiValueTextField::KEY_SEP . $fieldName . MultiValueTextField::KEY_SEP . $recordID,
            // html form name requires recordID to appear first, followed by nested fieldName
            'name' => "$this->name[$recordID][$fieldName]",
            'value' => $value,
            'tabindex' => $this->getAttribute('tabindex'),
        ];

        if ($this->disabled) $attrs['disabled'] = 'disabled';

        return HTML::createTag('input', $attrs);
    }

    /**
     * Render a single dropdown field
     */
    public function createMultiValueDropdownField($recordID, $fieldName, $value = null)
    {
        $selectAttrs = [
            'class' => 'text mventryfield mvdropdown '.($this->extraClass() ? $this->extraClass() : ''),
            // front-end javascript assumes recordID is last when adding new rows
            'id' => $this->id() .  MultiValueTextField::KEY_SEP . $fieldName . MultiValueTextField::KEY_SEP . $recordID,
            // html form name requires recordID to appear first, followed by nested fieldName
            'name' => "$this->name[$recordID][$fieldName]",
            'value' => $value,
            'tabindex' => $this->getAttribute('tabindex'),
        ];

        if ($this->disabled) $selectAttrs['disabled'] = 'disabled';


        /** @var array $options */
        $options = $this->recordSpec[$fieldName]['Options'];
        $optionTags = '';
        foreach ($options as $index => $title) {
            $optionAttrs = [
                'value' => $index,
                'selected' => $index == $value? 'selected' : '',
            ];
            $optionTags .= HTML::createTag('option', $optionAttrs, Convert::raw2xml($title));
        }

        return HTML::createTag('select', $selectAttrs, $optionTags);
    }

    public function performReadonlyTransformation()
    {
        $new = clone $this;
        $new->setReadonly(true);
        return $new;
    }

    private function isEmptyRecord($record): bool {
        foreach ($record as $fieldName => $value) {
            if(!empty($value)){
                return false;
            }
        }

        return true;
    }

    public function setValue($v, $data = NULL)
    {
        if (is_array($v)) {
            // we've been set directly via the post - lets prune any empty values
            foreach ($v as $recordID => $record) {
                if ($this->isEmptyRecord($record)) {
                    unset($v[$recordID]);
                }
            }
        }

        // get value from DB field
        if ($v instanceof MultiValueField) {
            $v = $v->getValues();
        }

        if (!is_array($v)) {
            $v = [];
        }

        parent::setValue($v);
    }

    public function setTag($tag)
    {
        $this->tag = $tag;
        return $this;
    }
}