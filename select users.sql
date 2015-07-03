SELECT  
    u.user_email as `E-mail`,
    MAX(CASE WHEN um.meta_key = 'act_role' THEN um.meta_value ELSE "" END) as `Role`,
    MAX(CASE WHEN um.meta_key = 'first_name' THEN um.meta_value ELSE "" END) as `First Name`,
    MAX(CASE WHEN um.meta_key = 'last_name' THEN um.meta_value ELSE "" END) as `Surname`,
    MAX(CASE WHEN um.meta_key = 'nickname' THEN um.meta_value ELSE "" END) as `Contact`,
    MAX(CASE WHEN um.meta_key = 'client_grade' THEN um.meta_value ELSE "" END) as `Client Grade`,
    MAX(CASE WHEN um.meta_key = 'direct_brand' THEN um.meta_value ELSE "" END) as `Direct Brand`,
    MAX(CASE WHEN um.meta_key = 'agent' THEN um.meta_value ELSE "" END) as `Agent`,
    MAX(CASE WHEN um.meta_key = 'birth_date' THEN um.meta_value ELSE "" END) as `Birth Date`,
    MAX(CASE WHEN um.meta_key = 'mobile_number' THEN um.meta_value ELSE "" END) as `Mobile Phone`,
    MAX(CASE WHEN um.meta_key = 'fax_number' THEN um.meta_value ELSE "" END) as `Fax`,
    MAX(CASE WHEN um.meta_key = 'billing_company' THEN um.meta_value ELSE "" END) as `Company`,
    MAX(CASE WHEN um.meta_key = 'billing_address_1' THEN um.meta_value ELSE "" END) as `Address 1`,
    MAX(CASE WHEN um.meta_key = 'billing_address_2' THEN um.meta_value ELSE "" END) as `Address 2`,
    MAX(CASE WHEN um.meta_key = 'billing_city' THEN um.meta_value ELSE "" END) as `City`,
    MAX(CASE WHEN um.meta_key = 'billing_postcode' THEN um.meta_value ELSE "" END) as `Postcode`,
    MAX(CASE WHEN um.meta_key = 'billing_state' THEN um.meta_value ELSE "" END) as `State`,
    MAX(CASE WHEN um.meta_key = 'billing_phone' THEN um.meta_value ELSE "" END) as `Phone`,
    MAX(CASE WHEN um.meta_key = 'shipping_address_1' THEN um.meta_value ELSE "" END) as `Home Address 1`,
    MAX(CASE WHEN um.meta_key = 'shipping_address_2' THEN um.meta_value ELSE "" END) as `Home Address 2`,
    MAX(CASE WHEN um.meta_key = 'shipping_city' THEN um.meta_value ELSE "" END) as `Home City`,
    MAX(CASE WHEN um.meta_key = 'shipping_postcode' THEN um.meta_value ELSE "" END) as `Home Postcode`,
    MAX(CASE WHEN um.meta_key = 'shipping_country' THEN um.meta_value ELSE "" END) as `Home Country`,
    MAX(CASE WHEN um.meta_key = 'shipping_state' THEN um.meta_value ELSE "" END) as `Home State`,
    MAX(CASE WHEN um.meta_key = 'myob_card_id' THEN um.meta_value ELSE "" END) as `MYOB Card ID`,
    MAX(CASE WHEN um.meta_key = 'myob_customer_card_id' THEN um.meta_value ELSE "" END) as `MYOB Customer Card ID`,
    u.user_url as `Web Site`,
    MAX(CASE WHEN um.meta_key = 'abn' THEN um.meta_value ELSE "" END) as `ABN`,
    MAX(CASE WHEN um.meta_key = 'business_type' THEN um.meta_value ELSE "" END) as `Business Type`,
    MAX(CASE WHEN um.meta_key = 'referred_by' THEN um.meta_value ELSE "" END) as `Referred By`,
    MAX(CASE WHEN um.meta_key = 'how_hear_about' THEN um.meta_value ELSE "" END) as `Lead Source`,
    MAX(CASE WHEN um.meta_key = 'pref_mob' THEN um.meta_value ELSE "" END) as `Mobile Phone Preferred`,
    MAX(CASE WHEN um.meta_key = 'pref_tel' THEN um.meta_value ELSE "" END) as `Phone Preferred`,
    -- MAX(CASE WHEN um.meta_key = 'mailing_list' THEN um.meta_value ELSE "" END) as `Added to mailing list`,
    -- MAX(CASE WHEN um.meta_key = 'facebook' THEN um.meta_value ELSE "" END) as `Facebook Username`,
    -- MAX(CASE WHEN um.meta_key = 'twitter' THEN um.meta_value ELSE "" END) as `Twitter Username`,
    -- MAX(CASE WHEN um.meta_key = 'gplus' THEN um.meta_value ELSE "" END) as `Google+ Username`,
    -- MAX(CASE WHEN um.meta_key = 'instagram' THEN um.meta_value ELSE "" END) as `Ingragram`,
    u.user_login,
    u.display_name,
    u.ID
FROM
    tt6164_users u
    LEFT JOIN tt6164_usermeta um
    ON ( um.`user_id` = u.ID)
WHERE
    u.ID in (10253,10806,10989,11359,11693,11973,12,12274,12419,12448,12756,13164,13303,13565,14260,14472,14480,14546,14565,14742,14794,14817,14820,14822,14829,14833,14834,14835,14840,14853,14854,14859,14860,14864,14866,182,4970,5963,6087,6224,6533,6892,7519,9,9566)      
GROUP BY
   u.ID