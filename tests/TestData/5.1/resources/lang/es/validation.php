<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | such as the size rules. Feel free to tweak each of these messages.
    |
    */

    'accepted'              => ':attribute debe ser aceptado.',
    'active_url'            => ':attribute no es una URL válida.',
    'after'                 => ':attribute debe ser una fecha posterior a :date.',
    'alpha'                 => ':attribute solo debe contener letras.',
    'alpha_dash'            => ':attribute solo debe contener letras, números y guiones.',
    'alpha_num'             => ':attribute solo debe contener letras y números.',
    'array'                 => ':attribute debe ser un conjunto.',
    'before'                => ':attribute debe ser una fecha anterior a :date.',
    'between'               => [
        'array'     => ':attribute tiene que tener entre :min - :max ítems.',
        'file'      => ':attribute debe pesar entre :min - :max kilobytes.',
        'numeric'   => ':attribute tiene que estar entre :min - :max.',
        'string'    => ':attribute tiene que tener entre :min - :max caracteres.',
    ],
    'boolean'               => 'El campo :attribute debe tener un valor verdadero o falso.',
    'confirmed'             => 'La confirmación de :attribute no coincide.',
    'date'                  => ':attribute no es una fecha válida.',
    'date_format'           => ':attribute no corresponde al formato :format.',
    'different'             => ':attribute y :other deben ser diferentes.',
    'digits'                => ':attribute debe tener :digits dígitos.',
    'digits_between'        => ':attribute debe tener entre :min y :max dígitos.',
    'email'                 => ':attribute no es un correo válido',
    'exists'                => ':attribute es inválido.',
    'filled'                => 'El campo :attribute es obligatorio.',
    'image'                 => ':attribute debe ser una imagen.',
    'in'                    => ':attribute es inválido.',
    'integer'               => ':attribute debe ser un número entero.',
    'ip'                    => ':attribute debe ser una dirección IP válida.',
    'max'                   => [
        'array'     => ':attribute no debe tener más de :max elementos.',
        'file'      => ':attribute no debe ser mayor que :max kilobytes.',
        'numeric'   => ':attribute no debe ser mayor a :max.',
        'string'    => ':attribute no debe ser mayor que :max caracteres.',
    ],
    'mimes'                 => ':attribute debe ser un archivo con formato: :values.',
    'min'                   => [
        'array'     => ':attribute debe tener al menos :min elementos.',
        'file'      => 'El tamaño de :attribute debe ser de al menos :min kilobytes.',
        'numeric'   => 'El tamaño de :attribute debe ser de al menos :min.',
        'string'    => ':attribute debe contener al menos :min caracteres.',
    ],
    'not_in'                => ':attribute es inválido.',
    'numeric'               => ':attribute debe ser numérico.',
    'regex'                 => 'El formato de :attribute es inválido.',
    'required'              => 'El campo :attribute es obligatorio.',
    'required_if'           => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_with'         => 'El campo :attribute es obligatorio cuando :values está presente.',
    'required_with_all'     => 'El campo :attribute es obligatorio cuando :values está presente.',
    'required_without'      => 'El campo :attribute es obligatorio cuando :values no está presente.',
    'required_without_all'  => 'El campo :attribute es obligatorio cuando ninguno de :values estén presentes.',
    'same'                  => ':attribute y :other deben coincidir.',
    'size'                  => [
        'array'     => ':attribute debe contener :size elementos.',
        'file'      => 'El tamaño de :attribute debe ser :size kilobytes.',
        'numeric'   => 'El tamaño de :attribute debe ser :size.',
        'string'    => ':attribute debe contener :size caracteres.',
    ],
    'string'                => 'The :attribute must be a string.',
    'timezone'              => 'El :attribute debe ser una zona válida.',
    'unique'                => ':attribute ya ha sido registrado.',
    'url'                   => 'El formato :attribute es inválido.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom'    => [
        'attribute-name'    => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes'    => [
        'address'                   => 'Dirección',
        'age'                       => 'Edad',
        'available'                 => 'Disponible',
        'city'                      => 'Ciudad',
        'content'                   => 'Contenido',
        'country'                   => 'País',
        'date'                      => 'Fecha',
        'day'                       => 'Día',
        'description'               => 'Descripción',
        'email'                     => 'E-mail',
        'excerpt'                   => 'Extracto',
        'first_name'                => 'Primer Nombre',
        'gender'                    => 'Género',
        'hour'                      => 'Hora',
        'last_name'                 => 'Apellido',
        'message'                   => 'Mensaje',
        'minute'                    => 'Minutos',
        'mobile'                    => 'Móvil',
        'month'                     => 'Mes',
        'name'                      => 'Nombre',
        'password'                  => 'Contraseña',
        'password_confirmation'     => 'Confirmación De La Contraseña',
        'phone'                     => 'Teléfono',
        'recaptcha_response_field'  => 'Recaptcha',
        'second'                    => 'Segundo',
        'sex'                       => 'Sexo',
        'size'                      => 'Tamaño',
        'subject'                   => 'Tema',
        'text'                      => 'Texto',
        'time'                      => 'Tiempo',
        'title'                     => 'Título',
        'username'                  => 'Nombre De Usuario',
        'year'                      => 'Año',
    ],
];
