<?php

/*
 * English names of the delivery options from the panel, by their code. A method with a euro price but no name
 * here stays off the English checkout (Checkout\Support\ShippingMethods): never a Polish name under /en/.
 */
return [
    'parcel_locker' => ['label' => 'InPost parcel locker', 'note' => 'In Poland, 1–2 working days'],
    'courier' => ['label' => 'InPost courier', 'note' => 'In Poland, to your door, 1 day'],
    'studio_pickup' => ['label' => 'Pickup at the studio', 'note' => 'Kraków, by appointment'],
];
