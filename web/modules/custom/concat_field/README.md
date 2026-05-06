# Concat Field

A Drupal 10 module that allows a selected set of fields to be concatenated together.

## Description

The Concat Field module provides a custom field type that automatically concatenates values from other fields on the same entity. This is useful for creating computed fields that combine multiple field values into a single searchable or displayable field.

## Features

- Custom field type for concatenating field values
- Configurable field selection through field settings
- Support for entity labels
- Automatic computation on entity save
- HTML tag stripping and whitespace normalization
- Support for various field types (text, entity reference, etc.)

## Installation

1. Place the module in your `modules/custom` or `modules/contrib` directory
2. Enable the module via Drush: `drush en concat_field`
3. Or enable via the admin interface at `/admin/modules`

## Usage

1. Add a "Concat field" to any entity type (node, taxonomy term, etc.)
2. Configure the field settings to select which fields should be concatenated
3. The field will automatically compute its value when the entity is saved
4. Use the "No output" formatter to hide the field from display if desired

## Migration from Drupal 7

This is a complete rewrite for Drupal 10 compatibility:

- Converted from hooks to plugins (FieldType, FieldWidget, FieldFormatter)
- Updated to use modern Drupal APIs
- Replaced Entity API wrapper with native field access
- Added proper namespacing and class structure
- Updated schema definitions and property definitions

## Requirements

- Drupal 10.x
- PHP 8.1 or higher

## License

GPL-2.0-or-later