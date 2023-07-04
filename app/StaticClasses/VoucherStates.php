<?php

namespace App\StaticClasses;

class VoucherStates
{
    const DRAFT         = 'BORRADOR';
    const SAVED         = 'CREADO';
    const SIGNED        = 'FIRMADO';
    const ACCEPTED      = 'ACEPTADO';
    const REJECTED      = 'NO AUTORIZADO';
    const SENDED        = 'ENVIADO';
    const RECEIVED      = 'RECIBIDA';
    const RETURNED      = 'DEVUELTA';
    const AUTHORIZED    = 'AUTORIZADO';
    const IN_PROCESS    = 'EN_PROCESO';
    const CANCELED      = 'ANULADO';
}
