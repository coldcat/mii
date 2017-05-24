<?php

namespace mii\web;

use mii\db\ORM;
use mii\util\HTML;
use mii\util\Upload;
use mii\valid\Validation;

class Form
{

    /**
     * @var Validation
     */
    public $validation;

    public $fields = [];

    public $select_data = [];

    public $labels = [];

    public $message_file;

    protected $_changed = [];

    protected $_model;

    protected $is_prepared = false;

    /**
     * @param null $data Initial data for form. If null then request->post() will be used
     */
    public function __construct($data = null) {

        $this->validation = new Validation();

        if (is_object($data) AND $data instanceof ORM) {
            $this->_model = $data;
            $data = $data->to_array();
        }

        if (count($data)) {
            foreach (array_intersect_key($data, $this->fields) as $key => $value) {
                $this->set($key, $value);
            }
        }
    }

    /**
     * Load form from _POST values or $data values
     * Return true if request method is post
     * @return bool
     */

    public function load($data = null) {

        if ($this->posted()) {
            $data = \Mii::$app->request->post();
        } elseif (is_object($data) AND $data instanceof ORM) {
            $this->_model = $data;
            $data = $data->to_array();
        }

        if (count($data)) {
            foreach (array_intersect_key($data, $this->fields) as $key => $value) {
                $this->set($key, $value);
            }
        }

        if (!$this->posted() AND !$this->is_prepared) {
            $this->prepare();
            $this->is_prepared = true;
        }

        return $this->posted();
    }

    public function posted() {
        return \Mii::$app->request->method() === Request::POST;
    }

    public function rules() {
        return [];
    }

    public function fields(): array {
        return $this->fields;
    }

    public function model(): ?ORM {
        return $this->_model;
    }

    public function changed_fields(): array {
        return array_intersect_key($this->fields, $this->_changed);
    }

    /**
     * Checks if the field (or any) was changed
     *
     * @param string|array|null $field_name
     * @return bool
     */

    public function changed($field_name = null): bool {
        if ($field_name === null) {
            return count($this->_changed) > 0;
        }

        if (is_array($field_name)) {
            return count(array_intersect($field_name, array_keys($this->_changed)));
        }

        return isset($this->_changed[$field_name]);
    }

    public function get(string $name) {
        return $this->fields[$name];
    }

    public function set(string $name, $value) {
        $this->_changed[$name] = true;
        return $this->fields[$name] = $value;
    }


    public function validate(): bool {

        $this->validation->rules($this->rules());

        $this->validation->data(\Mii::$app->request->post());

        $passed = $this->validation->check();

        if ($passed === false) {
            $this->check_prepared();
        }

        return $passed;
    }

    public function errors(): array {
        return $this->validation->errors($this->message_file);
    }

    public function errors_values() : array
    {
        return $this->validation->errors_values();
    }

    public function has_errors(): bool {
        return $this->validation->has_errors() > 0;
    }

    public function open($action = null, ?array $attributes = null): string {

        $this->check_prepared();

        $out = HTML::open($action, $attributes);

        $is_post = $attributes === null ||
            !isset($attributes['method']) ||
            strcasecmp($attributes['method'], 'post') === 0;

        if (\Mii::$app->request->сsrf_validation && $is_post) {

            $out .= HTML::hidden(\Mii::$app->request->csrf_token_name, \Mii::$app->request->csrf_token());
        }

        return $out;
    }

    public function close(): string {
        return HTML::close();
    }

    public function input($name, $attributes = null): string {
        return $this->field('input', $name, $attributes);
    }

    public function textarea($name, $attributes = null): string {
        return $this->field('textarea', $name, $attributes);
    }

    public function redactor($name, $attributes = null, $block = 'redactor', $options = []) {
        if ($attributes === null OR !isset($attributes['id'])) {
            $attributes['id'] = '__form_redactor__' . $name;
        }

        return block($block)
            ->set('textarea', $this->field('textarea', $name, $attributes))
            ->set('id', $attributes['id'])
            ->set('options', $options);
    }

    public function checkbox($name, $attributes = null): string {
        return $this->field('checkbox', $name, $attributes);
    }

    public function hidden($name, $attributes = null): string {
        return $this->field('hidden', $name, $attributes);
    }

    public function file($name, $attributes = null): string {
        return $this->field('file', $name, $attributes);
    }


    public function select($name, $attributes = null): string {
        return $this->field('select', $name, $attributes);
    }

    public function password($name, $attributes = null): string {
        return $this->field('password', $name, $attributes);
    }

    public function field($type, $name, $attributes = null): string {

        if (!array_key_exists($name, $this->fields)) {
            $this->fields[$name] = null;
        }

        switch ($type) {
            case 'input':
                return HTML::input($name, $this->fields[$name], $attributes);
            case 'hidden':
                return HTML::hidden($name, $this->fields[$name], $attributes);
            case 'textarea':
                return HTML::textarea($name, $this->fields[$name], $attributes);
            case 'checkbox':
                return HTML::checkbox($name, 1, (bool)$this->fields[$name], $attributes);
            case 'password':
                return HTML::password($name, $this->fields[$name], $attributes);
            case 'select':
                if ($attributes AND isset($attributes['multiple']) AND $attributes['multiple'] !== false) {
                    return HTML::select($name . '[]', $this->select_data[$name], $this->fields[$name], $attributes);
                }
                return HTML::select($name, $this->select_data[$name], $this->fields[$name], $attributes);
            case 'file':
                return HTML::file($name, $attributes);
        }
        throw new FormException("Wrong field type $type");
    }

    public function label($field_name, $label_name, $attributes = null): string {
        $this->labels[$field_name] = $label_name;

        return HTML::label($field_name, $label_name, $attributes);
    }

    public function uploaded($name): bool {

        return isset($_FILES[$name]) AND Upload::not_empty($_FILES[$name]) AND Upload::valid($_FILES[$name]);

    }

    public function check_prepared(): void {
        if (!$this->is_prepared) {
            $this->prepare();
            $this->is_prepared = true;
        }
    }


    public function prepare() {

    }


}
