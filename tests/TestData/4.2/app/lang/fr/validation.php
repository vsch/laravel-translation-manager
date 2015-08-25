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

    'accepted'              => 'Le champ :attribute doit être accepté.',
    'active_url'            => 'Le champ :attribute n\'est pas une URL valide.',
    'after'                 => 'Le champ :attribute doit être une date postérieure au :date.',
    'alpha'                 => 'Le champ :attribute doit seulement contenir des lettres.',
    'alpha_dash'            => 'Le champ :attribute doit seulement contenir des lettres, des chiffres et des tirets.',
    'alpha_num'             => 'Le champ :attribute doit seulement contenir des chiffres et des lettres.',
    'array'                 => 'Le champ :attribute doit être un tableau.',
    'before'                => 'Le champ :attribute doit être une date antérieure au :date.',
    'between'               => [
        'array'     => 'Le tableau :attribute doit avoir entre :min et :max éléments.',
        'file'      => 'Le fichier :attribute doit avoir une taille entre :min et :max kilo-octets.',
        'numeric'   => 'La valeur de :attribute doit être comprise entre :min et :max.',
        'string'    => 'Le texte :attribute doit avoir entre :min et :max caractères.',
    ],
    'boolean'               => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed'             => 'Le champ de confirmation :attribute ne correspond pas.',
    'date'                  => 'Le champ :attribute n\'est pas une date valide.',
    'date_format'           => 'Le champ :attribute ne correspond pas au format :format.',
    'different'             => 'Les champs :attribute et :other doivent être différents.',
    'digits'                => 'Le champ :attribute doit avoir :digits chiffres.',
    'digits_between'        => 'Le champ :attribute doit avoir entre :min and :max chiffres.',
    'email'                 => 'Le champ :attribute doit être une adresse email valide.',
    'exists'                => 'Le champ :attribute sélectionné est invalide.',
    'filled'                => 'Le champ :attribute est obligatoire.',
    'image'                 => 'Le champ :attribute doit être une image.',
    'in'                    => 'Le champ :attribute est invalide.',
    'integer'               => 'Le champ :attribute doit être un entier.',
    'ip'                    => 'Le champ :attribute doit être une adresse IP valide.',
    'max'                   => [
        'array'     => 'Le tableau :attribute ne peut avoir plus de :max éléments.',
        'file'      => 'Le fichier :attribute ne peut être plus gros que :max kilo-octets.',
        'numeric'   => 'La valeur de :attribute ne peut être supérieure à :max.',
        'string'    => 'Le texte de :attribute ne peut contenir plus de :max caractères.',
    ],
    'mimes'                 => 'Le champ :attribute doit être un fichier de type : :values.',
    'min'                   => [
        'array'     => 'Le tableau :attribute doit avoir au moins :min éléments.',
        'file'      => 'Le fichier :attribute doit être plus gros que :min kilo-octets.',
        'numeric'   => 'La valeur de :attribute doit être supérieure à :min.',
        'string'    => 'Le texte :attribute doit contenir au moins :min caractères.',
    ],
    'not_in'                => 'Le champ :attribute sélectionné n\'est pas valide.',
    'numeric'               => 'Le champ :attribute doit contenir un nombre.',
    'regex'                 => 'Le format du champ :attribute est invalide.',
    'required'              => 'Le champ :attribute est obligatoire.',
    'required_if'           => 'Le champ :attribute est obligatoire quand la valeur de :other est :value.',
    'required_with'         => 'Le champ :attribute est obligatoire quand :values est présent.',
    'required_with_all'     => 'Le champ :attribute est obligatoire quand :values est présent.',
    'required_without'      => 'Le champ :attribute est obligatoire quand :values n\'est pas présent.',
    'required_without_all'  => 'Le champ :attribute est requis quand aucun de :values n\'est présent.',
    'same'                  => 'Les champs :attribute et :other doivent être identiques.',
    'size'                  => [
        'array'     => 'Le tableau :attribute doit contenir :size éléments.',
        'file'      => 'La taille du fichier de :attribute doit être de :size kilo-octets.',
        'numeric'   => 'La valeur de :attribute doit être :size.',
        'string'    => 'Le texte de :attribute doit contenir :size caractères.',
    ],
    'string'                => 'Le champ :attribute doit être une chaîne de caractères.',
    'timezone'              => 'Le champ :attribute doit être un fuseau horaire valide.',
    'unique'                => 'La valeur du champ :attribute est déjà utilisée.',
    'url'                   => 'Le format de l\'URL de :attribute n\'est pas valide.',

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
        'address'                   => 'Adresse',
        'age'                       => 'Age',
        'available'                 => 'Disponible',
        'city'                      => 'Ville',
        'content'                   => 'Contenu',
        'country'                   => 'Pays',
        'date'                      => 'Date',
        'day'                       => 'Jour',
        'description'               => 'Description',
        'email'                     => 'E-mail',
        'excerpt'                   => 'Extrait',
        'first_name'                => 'Prénom',
        'gender'                    => 'Genre',
        'hour'                      => 'Heure',
        'last_name'                 => 'Nom',
        'message'                   => 'Message',
        'minute'                    => 'Minute',
        'mobile'                    => 'Portable',
        'month'                     => 'Mois',
        'name'                      => 'Nom',
        'password'                  => 'Mot de passe',
        'password_confirmation'     => 'Confirmation du mot de passe',
        'phone'                     => 'Téléphone',
        'recaptcha_response_field'  => 'Recaptcha',
        'second'                    => 'Seconde',
        'sex'                       => 'Sexe',
        'size'                      => 'Taille',
        'subject'                   => 'Sujet',
        'text'                      => 'Texte',
        'time'                      => 'Heure',
        'title'                     => 'Titre',
        'username'                  => 'Pseudo',
        'year'                      => 'Année',
    ],
];
