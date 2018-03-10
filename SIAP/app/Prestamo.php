<?php

namespace siap;

use Jenssegers\Date\Date;
use Carbon\Carbon; 
use Illuminate\Database\Eloquent\Model;
use siap\Cuenta;
use siap\Prestamo;

use DB;

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

    public static function estadoAnterior($idcuenta)
    {
        $cuenta = Cuenta::where('idcuenta',$idcuenta)->first();
        $prestamo = Prestamo::where('idprestamo',$cuenta->idprestamo)->first();
        return $prestamo->estadodos;
    }

    public static function cuenta_atraso($idcuenta)
    {


    try{

            $cuenta = DB::table('cuenta')->where('idcuenta','=',$idcuenta)->first();

            $prestamo = DB::table('prestamo')->where('idprestamo','=', $cuenta->idprestamo)->first();
            
            $cuentaAnt = DB::table('cuenta')->where('idcuenta','=',$prestamo->cuentaanterior)->first();

            $prestamoAnt = Prestamo::findOrFail($cuentaAnt->idprestamo);

            $tipo_credito_Ant = TipoCredito::findOrFail($cuentaAnt->idtipocredito);

            $liquidacionesAnt = DetalleLiquidacion::where('idcuenta','=',$cuentaAnt->idcuenta)
            ->orderby('iddetalleliquidacion', 'asc')
            ->get();

            $monto_capital = $prestamoAnt->monto;

            $atrasos = $cuentaAnt->cuotaatrasada;

            $bandera = 0;
            $ultimoPago = 0;
            $total = 0;
            $estadoAnt = "";

            //Se verifica que la última cuota NO este atrasada Si lo esta devuelve aumento
            foreach ($liquidacionesAnt as $ma) {
                if ($ma->estado == 'PENDIENTE' || $ma->estado == 'ATRASO') {
                    $bandera = $bandera + 1;
                }
            }

            //Se verifica cuál es la cantidad del último pago
            foreach ($liquidacionesAnt as $liq) {

                if($liq->abonocapital == "NO") {

                  $liq->monto = round($monto_capital, 2);

                  $monto_capital = $liq->monto - $liq->cuotacapital;

                }
                elseif($liq->abonocapital == "SI"){

                    $liq->monto = round($monto_capital,2);

                    $monto_capital = $liq->monto - $liq->totaldiario;

                }elseif ($liq->abonocapital == null) {  

                    // Obtenemos el ultimo monto capital del prestamo
                    if ($monto_capital < $prestamoAnt->cuotadiaria && $monto_capital > 0) {               
                        $ultimoPago = round($monto_capital,2);
                        $estadoAnt = $liq->estado;
                    }

                    //EXTRA: realizamos el calculo total atraso
                    if ($liq->estado == 'ATRASO' && $atrasos > 0 && $monto_capital > $prestamoAnt->cuotadiaria) {
                            $total = $total + round($prestamoAnt->cuotadiaria,2);
                            $total = round($total, 2);
                            $atrasos = $atrasos - 1;
                      }
                      elseif ($liq->estado == 'ATRASO' && $atrasos > 0 && $monto_capital < $prestamoAnt->cuotadiaria && $monto_capital > 0){
                            $total = $total + $ultimoPago + $ultimoPago*$tipo_credito_Ant->interes;
                            $total = round($total, 2);
                            $atrasos = $atrasos - 1;
                      }  
                    //ENDEXTRA
                    
                    //Cálculo de monto capital
                    $monto_capital = round($monto_capital, 2);
                    $interes = round($monto_capital * $tipo_credito_Ant->interes, 2);    
                    $totaldiario = round($prestamoAnt->cuotadiaria, 2);                    
                    $cuotacapital = round($prestamoAnt->cuotadiaria - $interes,2);      
                    
                    $monto_capital = $monto_capital - $cuotacapital;
                    
                }  

            }


            /*if ($estadoAnt == "ATRASO") {
                $var = $atrasos - 1;
                $total = round($var*$prestamoAnt->cuotadiaria + $ultimoPago, 2);
            }else{
                $total = round($atrasos*$prestamoAnt->cuotadiaria,2);
            }*/


        } catch(\Exception $e)
        {
          $total = 0;
        }

        return $total;
    }


}
