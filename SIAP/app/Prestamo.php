<?php

namespace siap;

use Jenssegers\Date\Date;
use Carbon\Carbon; 
use Illuminate\Database\Eloquent\Model;

class prestamo extends Model
{
    protected $table = 'prestamo';

    
    protected $primaryKey='idprestamo';

    protected $fillable = [
        'fecha', 'monto', 'cuotadiaria','estado','fechaultimapago','montooriginal','estadodos','cuentaanterior'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        
    ];
    
    //RElacion con tabla Cuenta
    public function cuenta(){
        return $this->hasMany(Cuenta::class);
    }

    public function getFechaAttribute($date)
    {
        if ($date != null) {
            return new Date($date);
        }
        
    }

    public static function actualizarEstado()
    {
        $fecha_actual = Carbon::now();
        $fecha_actual = $fecha_actual->format('Y-m-d');
        $prestamos = Prestamo::all();
        foreach ($prestamos as $prestamo) {
         if($prestamo->fechaultimapago < $fecha_actual && $prestamo->estadodos=='ACTIVO')
         {
             $prestamo->estadodos = 'VENCIDO';
             $prestamo->update();
         }
        }

        return 1;
    }
}
