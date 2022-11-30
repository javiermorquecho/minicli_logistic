<?php

namespace App\Command;

use Minicli\CommandController;

class TruckController extends CommandController
{
    private $even = 1.5;
    private $odd = 1;
    private $drivers_assigned = array();
    
    public function run($argv)
    {
        if ( ! isset( $argv[2] ) || !isset( $argv[3] ) ) {
            $this->getApp()->getPrinter()->display( "Two params are required" );
            return;
        }
        $addreses = $this->readfile( $argv[2] );
        $drivers  = $this->readfile( $argv[3] );
        
        $streets = $this->getStreets( $addreses );
        $names = $this->getNames( $drivers );
        $ss = $this->getSs( $streets, $names ); //get all results
        $result = $this->getSsTotal( $ss ); //get match between destination and drivers
        //$this->getApp()->getPrinter()->display( json_encode( $ss ) );
        $this->getApp()->getPrinter()->display( json_encode( $result ) );
    }
    
    private function readFile( $file ) {
        $file = fopen( __DIR__ . "/../../public/data/" . $file, "r" ) or exit("Unable to open file!");
        $result = array();

        while ( $line = fgets($file) ) {
            $result[] = $line;
        }

        return $result;
    }
    
    private function getStreets( $addreses ) {
        $result = array();
        
        foreach( $addreses as $address ) {
            $a = explode( ",", $address );
            $b = explode( " ", $a[0] );
            array_shift( $b );
            $result[] = array(
                'address' => str_replace( "\\n", '', $address ),
                'street' => implode( " ", $b ),
            );
        }
        
        return $result;
    }
    
    private function getNames( $names ) {
        $result = array();
        
        foreach( $names as $n ) {
            $a = explode( " ", $n );
            preg_match_all( '/[aeiou]/i', $a[0], $vowels );
            preg_match_all( '/[bcdfghjklmnpqrstvwxyz]/i', $a[0], $consonants );
            $result[] = array(
                'id' => count($result),
                'name' => $a[0],
                'fullname' => str_replace( "\\n", '', $n ),
                'vowels' => $vowels,
                'consonants' => $consonants,
            );
        }
        
        return $result;
    }
    
    private function getSs( $streets, $names ) {
        $result = array();
        
        foreach( $streets as $s ) {
            $l = strlen( $s['street'] );
            if ( $l % 2 == 0 ) {
                $result[] = array_merge( array( $s['address'], $s['street'] ), $this->getDriversScores( $names, 1, $s['street'] ) );
            }else{
                $result[] = array_merge( array( $s['address'], $s['street'] ), $this->getDriversScores( $names, 0, $s['street'] ) );
            }
        }

        return $result;
    }
    
    private function getDriversScores( $names, $even=0, $street ) {
        $result = array();
        
        foreach( $names as $n ) {
            if ( $even == 1 ) {
                $result[] = array(
                    'type' => 'even',
                    'id_driver' => $n['id'],
                    'name' => $n['name'],
                    'fullname' => $n['fullname'],
                    'vowels' => $n['vowels'],
                    'count' => count( $n['vowels'][0] ),
                    'pre' => count( $n['vowels'][0] ) * $this->even,
                    'ss' => $this->getSsWithCF( 
                        strlen( $street ), 
                        strlen( $n['name'] ), 
                        count( $n['vowels'][0] ) * $this->even 
                    ),
                );
            } else {
                $result[] = array(
                    'type' => 'odd',
                    'id_driver' => $n['id'],
                    'name' => $n['name'],
                    'fullname' => $n['fullname'],
                    'consonants' => $n['consonants'],
                    'count' => count( $n['consonants'][0] ),
                    'pre' => count( $n['consonants'][0] ) * $this->odd,
                    'ss' => $this->getSsWithCF( 
                        strlen( $street ), 
                        strlen( $n['name'] ), 
                        count( $n['consonants'][0] ) * $this->odd 
                    ),
                );
            }
        }
        
        return $result;
    }
    
    private function getSsWithCF( $a, $b, $ss ) {
        $cf = $this->getCommonFactor( $a, $b );
        if ( $cf > 0  ) {
            $ss = $ss * 1.50;
        }
        
        return $ss;
    }
    
    private function getCommonFactor( $a, $b ) {
        $min = ($a < $b ) ? $a : $b;
        $commomn_factors_count = 0;
        
        for ($i = 1; $i < $min/2; $i++) {
            if (($a%$i==0) && ($b%$i==0)) {
                $commomn_factors_count++;
            }
        }
        
        return $commomn_factors_count;
    }
    
    private function getSsTotal( $ss ) {
        $result = array();
        
        foreach( $ss as $s ) {
            $result[] = array(
                'adress' => $s[0],
                'driver' => $this->getBestDriver( $s ),
            );
        }
        
        return $result;
    }
    
    private function getBestDriver( $ss ) {
        $maxVal = 0;
        $maxKey = 0;

        for( $i=2; $i<count($ss); $i++ ) {
            if ( $ss[$i]['ss'] > $maxVal 
                && array_search( $ss[$i]['id_driver'], $this->drivers_assigned ) === false 
            ) {
                $maxVal = $ss[$i]['ss'];
                $maxKey = $i;
            }
        }
        $this->drivers_assigned[] = $ss[$maxKey]['id_driver'];
        
        return array(
                    $ss[$maxKey]['fullname'],
                    $ss[$maxKey]['ss'],
        );
    }
}
