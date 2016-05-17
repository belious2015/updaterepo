<?php
namespace Administration\Controller;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Administration\Model\Entity\Annonce;
use Administration\Model\Entity\Spider;
use Administration\Model\Entity\Moyenne;
use TesseractOCR;
use Doctrine\ORM\EntityManager;
use Zend\Exception;


class DataController extends AbstractActionController
{



/************************************************************ Controller actions **********************************************************/
	public function indexAction()
		{	
			//récuperer les annonces à partir de la base de données
			/*$annonces = $this->getAnnonces();
			
			foreach($annonces as $annonce){
				if(strpos($annonce['lien'], 'felbazar') !== false){
					echo utf8_encode($annonce['description']);
					echo "\n \n";
				}
			}*/

			$auth = $this->getServiceLocator()->get('Zend\Authentication\AuthenticationService');
			if ($auth->hasIdentity()) {
				$identity = $auth->getIdentity();
			}else{
				return $this->redirect()->toRoute('admin',array('controller'=>'auth', 'action'=>'login'));	
			}
			$this->layout('layout/admin-layout');
			$this->layout()->setVariable('user', $identity->getUser_name());
			$nb = $this->getNbAnnonces()[0][1];
			$sources = array();
			foreach($this->getSources() as $src){
				$sources[] = $src;
			}
			return new ViewModel(array('nb_annonce'=>$nb,'stats' => $this->statistics($sources, $nb)));
		}

	public function correctionAction()
		{
			$auth = $this->getServiceLocator()->get('Zend\Authentication\AuthenticationService');
			if ($auth->hasIdentity()) {
				$identity = $auth->getIdentity();
			}else{
				return $this->redirect()->toRoute('admin',array('controller'=>'auth', 'action'=>'login'));	
			}
			$this->layout('layout/admin-layout');
			$this->layout()->setVariable('user', $identity->getUser_name());
			$src = $this->getSources();
			return new ViewModel(array('sources'=>$src));
		}

	public function nettoyageAction(){
			$auth = $this->getServiceLocator()->get('Zend\Authentication\AuthenticationService');
			if ($auth->hasIdentity()) {
				$identity = $auth->getIdentity();
			}else{
				return $this->redirect()->toRoute('admin',array('controller'=>'auth', 'action'=>'login'));	
			}
			$this->layout('layout/admin-layout');
			$this->layout()->setVariable('user', $identity->getUser_name());
			$src = $this->getSources();
			return new ViewModel(array('sources'=>$src));
	}
		
	public function corAction(){
			$date_cur = date('Y-m-d');
			$time = strtotime($date_cur);
			$time = $time - (24*3600+60);
			$date = date('Y-m-d',$time);
			$id='toutes les sources';
			
			$this->typeAnnonce($id,$date);
			$this->typeBien($id,$date);
			$this->superficie($id,$date);
			$this->homogeniserPrix($id,$date);
			$this->pieces($id,$date);
			$this->adresse($id,$date);
			//$this->remplirPrix($id,$date);
			$this->telephone($id,$date);
			$this->email($id,$date);
	}
	
	public function netAction(){
				$date_max = date('Y-m-d');
				$time = strtotime($date_min);
				$time = $time - (28*24*3600+60);
				$date_min = date('Y-m-d',$time);
				$this->qualite('toutes les sources',array('prix','superficie','pieces'),$date_min,$date_max);
	}
		
		
		
	public function allAction(){
		$request = $this->getRequest();	
		if ($request->isPost())
		{
			
			$id = $request->getPost('id');
			$date = $request->getPost('date');
			$date = strtotime($date);
			$date = date('Y-m-d',$date);
			$maj = $this->typeAnnonce($id,$date);
			$maj = $this->typeBien($id,$date);
			$maj = $this->superficie($id,$date);
			$maj = $this->homogeniserPrix($id,$date);
			$maj = $this->pieces($id,$date);
			$maj = $this->adresse($id,$date);
			//$maj = $this->remplirPrix($id,$date);
        	$maj = $this->telephone($id,$date);
			$maj = $this->email($id,$date);
			
			$data = new JsonModel(array(
					'success' => true,
					'maj' => $maj
					
			));
			return $data;
			
		}	
	}
	public function PricesUpdateAction(){
		$request = $this->getRequest();	
		if ($request->isPost())
		{
			$this->emptyPrice();
			$wilayas = $this->getWilayaFromDb();
			foreach($wilayas as $wilaya){
				$communes = $this->getCommuneFromDb($wilaya['nom']);
				foreach($communes as $commune){
					$annonces = $this->getAnnoncesBy("commune",strtolower($commune['nom']));
					$prices = array();
					foreach($annonces as $annonce){
						if($annonce['type']=='vente'){
							switch($annonce['type_bien']){
								case 'terrain':
									if($annonce['prix']<400000) $prices[] = (int) $annonce['prix'];
									else if($annonce['superficie']>100) $prices[] = (int)($annonce['prix']/$annonce['superficie']);
									break;
								default:
									if($annonce['superficie']>50) $prices[] = (int)($annonce['prix']/$annonce['superficie']);
									break;
							}
						}
						
					}
					$moyenne = (count($prices)>0)?(int) array_sum($prices)/count($prices):0;
					$prix = new Moyenne();
					$prix->__set("commune",$commune['id']);
					$prix->__set("moyenne",(int)$moyenne);
					try{
						$this->getEntityManager()->persist($prix);
						$this->getEntityManager()->flush($prix);
					}catch(\Exception $e){
						echo $e->getMessage();
					}
				}
			}
			$data = new JsonModel(array(
					'success' => true,
					
			));
			return $data;
			
		}	
	}
	public function cleanAction(){
		$request = $this->getRequest();	
		if ($request->isPost())
		{
			
			$red = $request->getPost('redendance');
			$qua = $request->getPost('qualite');
			$date_min = $request->getPost('date_min');
			$date_min = strtotime($date_min);
			$date_min = date('Y-m-d',$date_min);
			$date_max = $request->getPost('date_max');
			$date_max = strtotime($date_max);
			$date_max = date('Y-m-d',$date_max);
			$source = $request->getPost('source');
			
			$red_list = array();
			foreach($red as $key => $value){
				if($value==1) $red_list[] = $key;
			}
			
			$qua_list = array();
			foreach($qua as $key => $value){
				if($value==1) $qua_list[] = $key;
			}
			$maj = $this->redendance($source,$red_list);
			$maj = $this->qualite($source,$qua_list,$date_min,$date_max);
			$maj = 0;
			$data = new JsonModel(array(
					'success' => true,
					'maj' => $maj
					
			));
			return $data;
			
		}
	}
	public function remplirPrixAction(){
		$request = $this->getRequest();	
		if ($request->isPost())
		{
			
			$id = $request->getPost('id');
			$date = $request->getPost('date');
			$date = strtotime($date);
			$date = date('Y-m-d',$date);
        	$maj = $this->remplirPrix($id,$date);
			$data = new JsonModel(array(
					'success' => true,
					'maj' => $maj
					
			));
			return $data;
			
		}
	}

	public function homoPrixAction(){
		//$id = $this->getEvent()->getRouteMatch()->getParam('id');
		$request = $this->getRequest();	
		if ($request->isPost())
		{
			
			$id = $request->getPost('id');
			$date = $request->getPost('date');
			$date = strtotime($date);
			$date = date('Y-m-d',$date);
        	$maj = $this->homogeniserPrix($id,$date);
			$data = new JsonModel(array(
					'success' => true,
					'maj' => $maj
					
			));
			return $data;
			
		}
	}

        public function typeAnnonceAction(){
		$request = $this->getRequest();	
		if ($request->isPost())
		{
			
			$id = $request->getPost('id');
			$date = $request->getPost('date');
			$date = strtotime($date);
			$date = date('Y-m-d',$date);
        	$maj = $this->typeAnnonce($id,$date);
			$data = new JsonModel(array(
					'success' => true,
					'maj' => $maj
					
			));
			return $data;
			
		}
        }

        public function typeBienAction(){
		$request = $this->getRequest();	
		if ($request->isPost())
		{
			
			$id = $request->getPost('id');
			$date = $request->getPost('date');
			$date = strtotime($date);
			$date = date('Y-m-d',$date);
        	$maj = $this->typeBien($id,$date);
			$data = new JsonModel(array(
					'success' => true,
					'maj' => $maj
					
			));
			return $data;
			
		}
        }
		public function superficieAction(){
			$request = $this->getRequest();	
			if ($request->isPost())
			{
				
				$id = $request->getPost('id');
				$date = $request->getPost('date');
				$date = strtotime($date);
				$date = date('Y-m-d',$date);
				$maj = $this->superficie($id,$date);
				$data = new JsonModel(array(
						'success' => true,
						'maj' => $maj
						
				));
				return $data;
				
			}
		}
		public function adresseAction(){
			$request = $this->getRequest();	
			if ($request->isPost())
			{
				
				$id = $request->getPost('id');
				$date = $request->getPost('date');
				$date = strtotime($date);
				$date = date('Y-m-d',$date);
				$maj = $this->adresse($id,$date); 
				$data = new JsonModel(array(
						'success' => true,
						'maj' => $maj
						
				));
				return $data;
				
			}	
		}
		public function emailAction(){
			$request = $this->getRequest();	
			if ($request->isPost())
			{
				
				$id = $request->getPost('id');
				$date = $request->getPost('date');
				$date = strtotime($date);
				$date = date('Y-m-d',$date);
				$maj = $this->email($id,$date); 
				$data = new JsonModel(array(
						'success' => true,
						'maj' => $maj
						
				));
				return $data;
				
			}		
		}
		public function telephoneAction(){
			$request = $this->getRequest();	
			if ($request->isPost())
			{
				
				$id = $request->getPost('id');
				$date = $request->getPost('date');
				$date = strtotime($date);
				$date = date('Y-m-d',$date);
				$maj = $this->telephone($id,$date); 
				$data = new JsonModel(array(
						'success' => true,
						'maj' => $maj
						
				));
				return $data;
				
			}		
		}
		public function nombrePiecesAction(){
			$request = $this->getRequest();	
			if ($request->isPost())
			{
				
				$id = $request->getPost('id');
				$date = $request->getPost('date');
				$date = strtotime($date);
				$date = date('Y-m-d',$date);
				$maj = $this->pieces($id,$date); 
				$data = new JsonModel(array(
						'success' => true,
						'maj' => $maj
						
				));
				return $data;
				
			}
		}



		/*
		 * Donner le pourcentage des annonces de chaque source dans la base de données
		 */
	public function statistics($sources, $nb){
		$stat = array();

		foreach($sources as $source){
			$p = $this->getNbAnnoncesBySource($source['domaine'])[0][1];
			$pourcentage = 0;
			if($nb!=0 ){
				$pourcentage = ($p/$nb) * 100;
			}
			$pourcentage = (int) $pourcentage;
			$stat[$source['domaine']] = $pourcentage;
			
		}
		return $stat;
	}


	   /*
	    * Homogéiniser les prix des annnonces
	    */
	
	public function homogeniserPrix($source,$date){
		echo 'date: '.$date;
		if($source=='toutes les sources' && $date=='') $annonces = $this->getAnnonces();
		else if($date=='') $annonce = $this->getAnnoncesBySource($source);
		else if($source=='toutes les sources') $annonces = $this->getAnnoncesByDate($date);
		else $annonces = $this->getAnnoncesBySourceDate($source,$date);
        $maj = 0;
        foreach($annonces as $annonce){
			if(strpos($annonce['prix'],"/M²")!=false || strpos($annonce['prix'],"/ M²")!=false) $mode = 'unite';
			else $mode = 'all';
			
            if($annonce['prix'] !='0' && $annonce['prix'] !='1'){
                $annonce['prix']  = (int)$this->getNewPrice($annonce['prix']);
				if($mode =='unite'){
					$sup = $this->getSup($annonce)[0]['superficie'];
					if($sup>0) $annonce['prix'] *=$sup;
				}
                $this->updatePrice($annonce);
                $maj +=1;
                }
            }
		return $maj;
	}

        /* 
	 * On remplit les annonces ou le prix est manquant par la moyenne des prix des bien de la meme categorie ayant le meme nombre de piece
         * se situant dans la meme wilaya
         */
	public function remplirPrix($source,$date){
		if($source=='toutes les sources' && $date=='') $annonces = $this->getAnnonces();
		else if($date=='') $annonces = $this->getAnnoncesBySource($source);
		else if($source=='toutes les sources') $annonces = $this->getAnnoncesByDate($date);
		else $annonces = $this->getAnnoncesBySourceDate($source,$date);
		$maj = 0;
		foreach($annonces as $annonce){
		        if($annonce['prix']=='1' || $annonce['prix']=='0'){
		                    $annonce['prix'] = (int) $this->averagePrice($this->getAveragePrice($annonce['wilaya'],$annonce['commune'],$annonce['pieces'],$annonce['type_bien']));
							$annonce['prix'] = (int)$this->arondissement($annonce['prix']);
							if($annonce['prix']!=0){
								$this->updatePrice($annonce);
								$maj +=1;
							}
		            }
		    }
		return $maj;
    }
	public function arondissement($price){
		$len = strlen((string)$price);
		$ret = $price / pow(10,($len-1));
		if($len<=3) $ret = ($this->round_up($ret,1))* pow(10,($len-1));
		else if(3<$len && $len<=6) $ret = ($this->round_up($ret,2))* pow(10,($len-1));
		else if(6<$len && $len<=9) $ret = ($this->round_up($ret,3))* pow(10,($len-1));
		else if(9<$len) $ret = ($this->round_up($ret,4))* pow(10,($len-1));
		else $ret = ($this->round_up($ret,2))* pow(10,($len-1));
		return (int) $ret;
	}
	
	function round_up ( $value, $precision ) { 
		$pow = pow ( 10, $precision ); 
		return ( ceil ( $pow * $value ) + ceil ( $pow * $value - ceil ( $pow * $value ) ) ) / $pow; 
	}
   
	/*
	 * Donner la moyenne d'un tableau de montant
	 */
    public function averagePrice($prices){
        $ret = 0;
        $nb=0;
        foreach($prices as $price){
                $ret += (int) $price['prix'];
                $nb +=1;
        }
        if($nb != 0) return ($ret/$nb);
		else return 0;
    }
	/*
	 * Extraire le montant de l'annonce à partir d'une chaine de caractères et le convertir en dinar
	 */
    public function getNewPrice($price){
		if(strlen($price)<2) return 0;
		$price = strtolower($price);
		if(strpos($price,"centim")!=false || strpos($price,"cts")!=false || strpos($price,'سنتم')!=false) $unite = 'CA';
		else if(strpos($price,"da")!=false || strpos($price,"dinar")!=false || strpos($price,"dinnar")!=false ||
						strpos($price,"دج")!=false || strpos($price,"دينار")!=false ) $unite = 'DA';
		else $unite = 'inconnue';
		
		$price = str_replace(' ', '', $price);		
		if(preg_match_all('/\d+(\.\d+)?/', $price, $matches)){
			$num = $matches[0][0];
		}else return 0;
	    if($num == $price) return (int) $price;
		$num = (int) $num;
		if(strlen($num)>6) return $num;
		if(strpos($price, 'milliard') !== false || strpos($price, 'miliard') !== false || strpos($price,  'مليار') !== false ||
		   strpos($price,  'ملايير')!== false){
			if($unite=='DA') $num *=1000000000;
			else if($unite=='CA' || $unite=='inconnue') $num *=10000000;
			return $num;
		}else if(strpos($price, 'million') !== false || strpos($price, 'unités') !== false || strpos($price, 'milion') !== false || strpos($price, 'مليون') !== false ||
					strpos($price, 'ملايين') !== false || strpos($price, 'ملاين') !== false){
			if($unite=='DA') $num *=1000000;
			else if($unite=='CA' || $unite=='inconnue') $num *=10000;
			return $num;
			
		}else if(strpos($price, 'mille') !== false || strpos($price, 'mile') !== false || strpos($price,  'الف') !== false ||
		   strpos($price, 'الاف')!== false){
			if($unite=='DA') $num *=1000;
			else if($unite=='CA' || $unite=='inconnue') $num *=10;
			return $num;
		}else return $num;
	}

	/*
	 * Extraire le type de l'annonce à partir du titre et de la description de l'annonce
	 */
    public function typeAnnonce($source,$date){
        $maj = 0;
		if($source=='toutes les sources' && $date=='') $annonces = $this->getAnnonces();
		else if($date=='') $annonce = $this->getAnnoncesBySource($source);
		else if($source=='toutes les sources') $annonces = $this->getAnnoncesByDate($date);
		else $annonces = $this->getAnnoncesBySourceDate($source,$date);
        foreach($annonces as $annonce){
		    $annonce['type'] = $this->getType($annonce['titre']." ".$annonce['description']." ".$annonce['lien']);
		    $this->updateType($annonce);
		    $maj +=1;
            }
        return $maj; 
    }
	/*
	 * Extraire la superifice du bien de l'annonce 
	 */
	public function superficie($source,$date){
		$maj = 0;
		if($source=='toutes les sources' && $date=='') $annonces = $this->getAnnonces();
		else if($date=='') $annonce = $this->getAnnoncesBySource($source);
		else if($source=='toutes les sources') $annonces = $this->getAnnoncesByDate($date);
		else $annonces = $this->getAnnoncesBySourceDate($source,$date);
        foreach($annonces as $annonce){
		    $annonce['superficie'] = $this->getSuperficie($annonce['superficie'].",".$annonce['description'].",".$annonce['pieces']);
		    $this->updateSuperficie($annonce);
		    $maj +=1;
            }
        return $maj;	
	}
	/*
	 * Extraire la superifice du bien de l'annonce 
	 */
	public function pieces($source, $date){
		$maj = 0;
		if($source=='toutes les sources' && $date=='') $annonces = $this->getAnnonces();
		else if($date=='') $annonce = $this->getAnnoncesBySource($source);
		else if($source=='toutes les sources') $annonces = $this->getAnnoncesByDate($date);
		else $annonces = $this->getAnnoncesBySourceDate($source,$date);
        foreach($annonces as $annonce){
			$nbp = $this->getNbPieces($annonce['titre']." ".$annonce['pieces']." ".$annonce['description']);
			if($nbp!=0){
				$annonce['pieces'] = $nbp;
				$this->updatePieces($annonce);
				$maj +=1;
			}else{
				$pieces = str_replace(' ', '', $annonce['pieces']);		
				if(preg_match_all('/\d+(\.\d+)?/', $pieces, $matches)){
					$num = $matches[0][0];
					$annonce['pieces'] = $num;
					$this->updatePieces($annonce);
					$maj +=1;
				}
			}
            }
        return $maj;	
	}
	
	/*
	 * Extraire l'adresse(quartier,commune,wilaya) à partir des champs wilaya,commune,quartier,titre,lien et description
	 */
	public function adresse($source,$date){
		$maj = 0;
		if($source=='toutes les sources' && $date=='') $annonces = $this->getAnnonces();
		else if($date=='') $annonce = $this->getAnnoncesBySource($source);
		else if($source=='toutes les sources') $annonces = $this->getAnnoncesByDate($date);
		else $annonces = $this->getAnnoncesBySourceDate($source,$date);
		$wilayas = $this->getWilayaFromDb();
        foreach($annonces as $annonce){
			//set_time_limit(20) ;
			if($annonce['commune']==null) $annonce['commune'] = '';
				$wil = $this->getWilaya($annonce['wilaya'],$annonce['quartier']." ".$annonce['titre']." ".$annonce['description']." ".$annonce['lien'],$wilayas);
				if($wil != null){
					$annonce['wilaya'] = $wil;
					$this->updateWilaya($annonce);
				}else{
					$annonce['wilaya'] = "inconnue";
					$this->updateWilaya($annonce);	
				}
				$com = $this->getCommune($annonce['commune'],$annonce['wilaya'],$annonce['quartier']." ".$annonce['titre']." ".$annonce['description']." ".$annonce['lien']);
				if($com != null){
					$annonce['commune'] = $com;
					$this->updateCommune($annonce);
					if($this->existe($wilayas,"nom",$annonce['wilaya'])==false || $annonce['wilaya']=="inconnue" || $annonce['wilaya']==""){
						$annonce['wilaya'] = $this->getWilayaOf($annonce['commune'])[0]['nom'];
						$this->updateWilaya($annonce);
					}
					$place = $this->getPlace($com, $annonce['quartier']." ".$annonce['titre']." ".$annonce['description']." ".$annonce['lien']);
					if($place != null){
						$annonce['quartier'] = $place;
						$this->updatePlace($annonce);
					}
				}else{
					$annonce['commune'] = "inconnue";
					$this->updateCommune($annonce);
				}
				$maj +=1;
            }
        return $maj;		
	}
	/*
	 * Récuperer le type de l'annone à partir du chaine de caractéres
	 */
    public function getType($titre){
	$titre = utf8_decode($titre);
        $titre = strtolower($titre);
	echo $titre;
        if ((strpos($titre, 'terrain') !== false) || (strpos($titre, 'terain') !== false) || (strpos($titre, 'terre') !== false) 
                    || (mb_strpos($titre, utf8_decode('ارض')) !== false) || (mb_strpos($titre, utf8_decode('اراضي')) !== false)) {
            return "vente";
    	}
        else if ((strpos($titre, 'vend') !== false) || (strpos($titre, 'vente') !== false) || (strpos($titre, 'vendre') !== false) 
                    || (mb_strpos($titre, utf8_decode('بيع')) !== false)) {
            return "vente";
    	}
	    else if ((strpos($titre, 'colocation') !== false) || (strpos($titre, 'colo') !== false) || (mb_strpos($titre, utf8_decode('كراء مشترك')) !== false) ) {
            return "colocation";
    	}
        else if ((strpos($titre, 'location') !== false) || (strpos($titre, 'loue') !== false) || (mb_strpos($titre, utf8_decode('كراء')) !== false) ) {
            return "location";
		}
        else if ((strpos($titre, 'echange') !== false) || (strpos($titre, 'échange') !== false) || (mb_strpos($titre, utf8_decode('تبادل')) !== false) ) {
            return "echange";
		}
        else return "annonce";
    }
	public function getNbPieces($chaine){
		$chaine = strtolower($chaine);
		if ((strpos($chaine, 'f1') !== false)) return 'F1';
		else if ((strpos($chaine, 'f2') !== false)) return 'F2';
		else if ((strpos($chaine, 'f3') !== false)) return 'F3';
		else if ((strpos($chaine, 'f4') !== false)) return 'F4';
		else if ((strpos($chaine, 'f5') !== false)) return 'F5';
		else if ((strpos($chaine, 'f6') !== false)) return 'F6';
		else if ((strpos($chaine, 'f7') !== false)) return 'F7';
		else if ((strpos($chaine, 'f8') !== false)) return 'F8';
		else if ((strpos($chaine, 'f9') !== false)) return 'F9';
		else if ((strpos($chaine, 'f10') !== false)) return 'F10';
		else return 0;
	}


	/*
	 * Récuperer le type du bien à partir du titre, de la description et du lien de l'annonce
	 */
	public function typeBien($source,$date){
        $maj = 0;
		if($source=='toutes les sources' && $date=='') $annonces = $this->getAnnonces();
		else if($date=='') $annonce = $this->getAnnoncesBySource($source);
		else if($source=='toutes les sources') $annonces = $this->getAnnoncesByDate($date);
		else $annonces = $this->getAnnoncesBySourceDate($source,$date);
        foreach($annonces as $annonce){
			if(strpos($annonce['lien'], $source) !== false || $source=='toutes les sources'){
				$annonce['type_bien'] = $this->getTypeBien($annonce['titre']." ".$annonce['lien']);
				$this->updateTypeBien($annonce);
				$maj +=1;
			}
        }
        return $maj; 
	}
	/*
	 * Rechercher le type du bien dans une chaine de caracteres
	 */
	public function getTypeBien($titre){
        $titre = strtolower($titre);
		if ((strpos($titre, 'niveau de villa') !== false) || (strpos($titre, 'niveau de vila') !== false)  
                    || (strpos($titre, utf8_decode('فيلا مستوى')) !== false)) {
            return "niveau de villa";
    	}
        else if ((strpos($titre, 'villa') !== false) || (strpos($titre, 'vila') !== false) || (strpos($titre, 'maison') !== false) 
                    || (strpos($titre, utf8_decode('منزل')) !== false) || (strpos($titre, utf8_decode('دار')) !== false)) {
            return "villa";
    	}
		else if ((strpos($titre, 'carcasse') !== false) || (strpos($titre, utf8_decode('هيكل')) !== false)) {
            return "carcasse";
    	}
        else if ((strpos($titre, 'appartement') !== false) || (strpos($titre, 'apartement') !== false) || (strpos($titre, 'logement') !== false)  
                    || (strpos($titre, utf8_decode('شقة')) !== false)) {
            return "appartement";
    	}
        else if ((strpos($titre, 'hangar') !== false) || (strpos($titre, 'hangare') !== false) || (strpos($titre, utf8_decode('حظيرة')) !== false)) {
            return "hangar";
    	}
        else if ((strpos($titre, 'studio') !== false) || (strpos($titre, 'chambre') !== false) || (strpos($titre, utf8_decode('ستوديو')) !== false)) {
            return "studio";
    	}
        else if ((strpos($titre, 'garage') !== false) || (strpos($titre, 'cabinet') !== false) || (strpos($titre, 'local') !== false) ||  (strpos($titre, 'bureau') !== false)
			|| (strpos($titre, utf8_decode('مكتب')) !== false) || (strpos($titre, utf8_decode('محل')) !== false)) {
            return "local";
    	}
        else if ((strpos($titre, 'terrain') !== false) || (strpos($titre, 'terain') !== false) || (strpos($titre, 'terre') !== false) 
                    || (strpos($titre, utf8_decode('ارض')) !== false) || (strpos($titre, utf8_decode('أرض')) !== false) || (strpos($titre, utf8_decode('اراضي')) !== false)|| (strpos($titre,utf8_decode('مزرعة')) !== false)) {
            return "terrain";
    	}
		else if ((strpos($titre, 'bungalow') !== false) || (strpos($titre, 'bangalo') !== false) || (strpos($titre, 'bingalo') !== false) 
                    || (strpos($titre, utf8_decode('جناح صغير')) !== false) || (strpos($titre, utf8_decode('بنقالو')) !== false)) {
            return "bungalow";
    	}
		else if ((strpos($titre, 'usine') !== false) || (strpos($titre, 'usin') !== false)  
                    || (strpos($titre, utf8_decode('مصنع')) !== false)) {
            return "usine";
    	}
		else if ((strpos($titre, 'duplex') !== false) || (strpos($titre, 'duplexe') !== false)  
                    || (strpos($titre,utf8_decode('دوبلكس')) !== false) || (strpos($titre,utf8_decode('دبلكس')) !== false)) {
            return "duplex";
    	}
		else if ((strpos($titre, 'immeuble') !== false) || (strpos($titre, 'imeuble') !== false)  
                    || (strpos($titre,utf8_decode('بناء')) !== false) || (strpos($titre,utf8_decode('عمارة')) !== false)) {
            return "immeuble";
    	}
		else if ((strpos($titre, 'chalet') !== false) || (strpos($titre, 'chaler') !== false)  
                    || (strpos($titre,utf8_decode('الشاليه')) !== false) || (strpos($titre,utf8_decode('شالي')) !== false)) {
            return "chalet";
    	}
        else return "bien immobilier";
	}
	
	/*
	 * Extraire la superficie à partir d'une chaine de caractére en utilisant des mots clés
	 */
	public function getSuperficie($superficie){
		$new_sup = $this->getMatch($superficie,array(' ', ',', '-','!','?','/' ));
		if ($new_sup!=null) return $new_sup;
		else return 0;
		
	}
	function getMatch($text,$delimiters){
		$set = $this->explodeX($delimiters, $text);
		$len = count($set);
		$pattern = "/[^0-9]?[(m|M)][2]/";
		for($i=0; $i<$len; $i++){
			if(is_numeric($set[$i]) && $set[$i]!="0" ){
				if(($set[$i+1]=="M" || $set[$i+1]=="m" || $set[$i+1]=="m²" || $set[$i+1]=="M²" || $set[$i+1]=="M2" || $set[$i+1]=="m2"))
					return $set[$i];
				else if($set[$i+1]=="hectares" || $set[$i+1]=="hectare"|| $set[$i+1]=="h")
					return (int)(((float) $set[$i]) * 10000);
			}else if(preg_match($pattern, $set[$i])==1){
			  	preg_match_all('!\d+!', $set[$i], $matches);
				return $matches[0][0];
			}
		}
		return null;
	}
	/*
	 * Extraire le nom de la wilaya à partir d'une chaine de caractére en utilisant la table wilaya de la base de données
	 */
	public function getWilaya($wilaya,$chaine,$wilayas){
		if($this->existe($wilayas,"nom",$wilaya)){
			return $wilaya;
		}else{
			$chaine = $wilaya." ".$chaine;
			$chaine = strtolower($chaine);
			$words = $this->get_all_substrings($chaine,array(' ', ',', '-','!','?','.','/' ),100);
			$min_distance = 3000;
			$new_wil = '';
			foreach($wilayas as $item){
				$item['nom'] = strtolower($item['nom']);
				if(strpos($chaine, $item['nom']) !== false || strpos($chaine, $item['nom_arabe']) !== false) return $item['nom'];
				foreach($words as $word){
						$d = 0;
						$d = levenshtein($item['nom'],$word);
						if($d<$min_distance  && $d!=-1){
							$min_distance = $d;
							$new_wil = $item['nom'];
						}else{
							$d1 = levenshtein($item['nom_arabe'],$word);
							if($d1<$min_distance  && $d1!=-1){
								$min_distance = $d1;
								$new_wil = $item['nom'];
							}
						}
				}
				
			}
			if($min_distance<2){
				return $new_wil;
			}else return null;
		}
	}
	public function explodeX( $delimiters, $string )
	{
		return explode( chr( 1 ), str_replace( $delimiters, chr( 1 ), $string ) );
	}
	function get_all_substrings($input, $delim, $limite) {
		$arr = $this->explodeX($delim, $input);
		$out = array();
		for ($i = 0; $i < count($arr); $i++) {
			for ($j = $i; $j < count($arr); $j++) {
				$inter = implode(' ', array_slice($arr, $i, $j - $i + 1));
				if(strlen($inter)<$limite) $out[] = $inter;
			}       
		}
		return $out;
	}
	/*
	 * Extraire la superficie à partir d'une chaine de caractére en utilisant la table wilaya de la base de données
	 */
	public function getCommune($commune,$wilaya,$chaine){
		if($wilaya=='inconnue'){
			$communes = $this->getAllCommuneFromDb();
		}else $communes = $this->getCommuneFromDb($wilaya);
		
		if($this->existe($communes,"nom",$commune)){
			return $commune;
		}else{
			$chaine = $commune." ".$chaine;
			$chaine = strtolower($chaine);
			$words = $this->get_all_substrings($chaine,array(' ', ',', '-','!','?','.','/' ),100);
			$min_distance = 3000;
			$new_com = '';
			foreach($communes as $item){
				$item['nom'] = strtolower($item['nom']);
				if($item['nom']!=$wilaya && strpos($chaine, $item['nom']) !== false || strpos($chaine, $item['nom_arabe']) !== false) return $item['nom'];
				foreach($words as $word){
					set_time_limit(20);
						$d = 0;
						$d1 = 0;
						$d = levenshtein($item['nom'],$word);
						if($d<$min_distance && $d!=-1 && $item['nom']!=$wilaya){
							$min_distance = $d;
							$new_com = $item['nom'];
						}else if($d==$min_distance){
							if($item['nom']!=$wilaya){
								$min_distance = $d;
								$new_com = $item['nom'];	
							}
						}else{
							$d1 = levenshtein($item['nom_arabe'],$word);
							if($d1<$min_distance && $d1!=-1){
								$min_distance = $d1;
								$new_com = $item['nom'];
							}
						}
					
				}
			}
			if($min_distance<2){
				return $new_com;
			}else return null;
		}
		
	}
	
	public function getPlace($commune,$chaine){
		$id_commune = $this->getIdCommune($commune)[0]['id'];
		if($id_commune) $places = $this->getPlacesByCommune($id_commune);
		else $place =array();
		
		$chaine = $commune." ".$chaine;
		$chaine = strtolower($chaine);
		$words = $this->get_all_substrings($chaine,array(' ', ',', '-','!','?','.','/' ),100);
		$min_distance = 3000;
		$new_place = '';
		foreach($places as $item){
			set_time_limit(20);
			if((strpos($chaine, $item['design']) !== false && strlen($item['design'])>=3)){
				if($this->existeX($words,$item['design'])) return $item['design'];
			}else if(strpos($item['design'],$chaine) !== false){
				if(levenshtein($item['design'],$chaine)<2) return $item['design'];
			}else{
				foreach($words as $word){
					$d = 0;
					$d = levenshtein($item['design'],$word);
					if($d<$min_distance && $d!=-1){
						$min_distance = $d;
						$new_place = $item['design'];
					}else if($d==$min_distance){
						if($item['design']!=$commune){
							$min_distance = $d;
							$new_place = $item['design'];	
						}
					}
				}
			}
		}
		if($min_distance<2){
			return $new_place;
		}else return null;
	}
	
	public function email($source,$date){
		$maj = 0;
		if($this->is_connected()){
			if($source=='toutes les sources' && $date=='') $annonces = $this->getAnnonces();
			else if($date=='') $annonce = $this->getAnnoncesBySource($source);
			else if($source=='toutes les sources') $annonces = $this->getAnnoncesByDate($date);
			else $annonces = $this->getAnnoncesBySourceDate($source,$date);
			foreach($annonces as $annonce){
				set_time_limit(20);
				if (filter_var($annonce['email_annonceur'], FILTER_VALIDATE_URL)){
					if($annonce['email_annonceur']!="Indisponible"){
						$img = $this->downloadEmailImage($annonce['email_annonceur']);
						$ret = $this->getTextFromImage($img);
						if($ret!=null){
							$annonce['email_annonceur'] = $ret;
							$this->updateEmail($annonce);
							$maj +=1;
						}
					}
				}
			}
		}
        return $maj;
	}
	public function telephone($source,$date){
		$maj = 0;
		if($this->is_connected()){
			if($source=='toutes les sources' && $date=='') $annonces = $this->getAnnonces();
			else if($date=='') $annonce = $this->getAnnoncesBySource($source);
			else if($source=='toutes les sources') $annonces = $this->getAnnoncesByDate($date);
			else $annonces = $this->getAnnoncesBySourceDate($source,$date);
			foreach($annonces as $annonce){
				set_time_limit(20);
				if (filter_var($annonce['tel_annonceur'], FILTER_VALIDATE_URL)){
					if($annonce['tel_annonceur']!="Indisponible"){
						$img = $this->downloadEmailImage($annonce['tel_annonceur']);
						$ret = $this->getTextFromImage($img);
						if($ret!=null){
							$annonce['tel_annonceur'] = $ret;
							$this->updateTelephone($annonce);
							$maj +=1;
						}
					}
				}
			}
		}
        return $maj;	
	}
	
	
	public function redendance($source,$red_list){
		if($source = 'toues les sources') $annonces = $this->getAnnonces();
		else $annonces = $this->getAnnoncesBySource($source);
		foreach($annonces as $annonce){
			foreach($annonces as $annonce_1){
				if($annonce['id_annonce'] != $annonce_1['id_annonce']){
					if($this->compareAnnonces($annonce,$annonce_1,$red_list)){
						$this->deleteAnnonce($annonce_1);
					}
				}
			}
		}
	}
	public function qualite($source,$qua_list,$date_min,$date_max){
		if($source = 'toues les sources') $annonces = $this->getAnnoncesByDateInterval($date_min,$date_max);
		else $annonces = $this->getAnnoncesBySourceDateInreval($source,$date_min,$date_max);
		foreach($annonces as $annonce){
			foreach($qua_list as $field){
				if($annonce[$field]=='' || $annonce[$field]==' ' || $annonce[$field]=='1' || $annonce[$field]=='0'){
					$this->deleteAnnonce($annonce);
				}
			}
		}
	}
	
	public function compareAnnonces($annonce_1,$annonce_2,$critere){
		foreach($critere as $c){
			if($c == 'adresse'){
				if($annonce_1['wilaya'] !=$annonce_2['wilaya']) return false;
				if($annonce_1['commune'] !=$annonce_2['commune']) return false;
				if($annonce_1['quartier'] !=$annonce_2['quartier']) return false;
			}
			else if($annonce_1[$c] !=$annonce_2[$c]) return false;
		}
		return true;
	}
	
	
	public function existe($array, $key, $val) {
		foreach ($array as $item)
			if (isset($item[$key]) && strtolower($item[$key]) == strtolower($val))
				return true;
		return false;
	}
	public function existeX($stringArray,$word){
		foreach ($stringArray as $item)
			if (isset($item) && strtolower($item) == strtolower($word))
				return true;
		return false;
	}
	public function getTextFromImage($img){
		$publicDir = getcwd() . '/public';
		$tesseract = new TesseractOCR($img);
		$ret = $tesseract->recognize();
		//echo nl2br("path: $img email:$ret");
		$ret = str_replace("®","@",$ret);
		$ret = str_replace("'","",$ret);
		return utf8_encode($ret);
	}
	public function downloadEmailImage($link){
		$path = getcwd() . '/public/tmp/current_email.jpg';
		$fp = fopen($path, 'wb');
		try{
			fwrite($fp, file_get_contents($link));
		}catch(Exception $e){
			return null;
		}
		fclose($fp);
		return $path;
	}
	


	public function is_connected()
	{
		$connected = @fsockopen("www.google.com", 80); 
		if ($connected){
			$is_conn = true; 
			fclose($connected);
		}else{
			$is_conn = false;
		}
		return $is_conn;
	
	}
	
	
	
	
    /*************************************************************** DATABASE ACTIONS ***********************************************************/

	    /**
	    * @return EntityManager
	    */
	public function getEntityManager()
	{
		return $this
					->getServiceLocator()
					->get('Doctrine\ORM\EntityManager');
	
	}

	public function getAnnonces(){
		return $this->getEntityManager()->createQuery("select e.id_annonce, e.titre, e.prix, e.superficie, e.etage, e.pieces, e.lien, e.nom_annonceur,
									e.tel_annonceur, e.email_annonceur, e.quartier,e.commune, e.wilaya, e.description,
									e.type, e.type_bien, e.date
									from Administration\Model\Entity\Annonce as e")->execute();
		
		
	}
	public function getAnnoncesBySource($source){
		return $this->getEntityManager()->createQuery("select e.id_annonce, e.titre, e.prix, e.superficie, e.etage, e.pieces, e.lien, e.nom_annonceur,
									e.tel_annonceur, e.email_annonceur, e.quartier,e.commune, e.wilaya, e.description,
									e.type, e.type_bien, e.date
									from Administration\Model\Entity\Annonce as e where e.lien LIKE '%{$source}%'")->execute();
		
		
	}
	public function getAnnoncesByDate($date){
		return $this->getEntityManager()->createQuery("select e.id_annonce, e.titre, e.prix, e.superficie, e.etage, e.pieces, e.lien, e.nom_annonceur,
									e.tel_annonceur, e.email_annonceur, e.quartier,e.commune, e.wilaya, e.description,
									e.type, e.type_bien, e.date
									from Administration\Model\Entity\Annonce as e where e.date >='$date'")->execute();
		
		
	}
	public function getAnnoncesByDateInterval($date_min,$date_max){
		return $this->getEntityManager()->createQuery("select e.id_annonce, e.titre, e.prix, e.superficie, e.etage, e.pieces, e.lien, e.nom_annonceur,
									e.tel_annonceur, e.email_annonceur, e.quartier,e.commune, e.wilaya, e.description,
									e.type, e.type_bien, e.date
									from Administration\Model\Entity\Annonce as e where e.date >='$date_min' and e.date<='$date_max'")->execute();
		
		
	}
	public function getAnnoncesBySourceDateInreval($source,$date_min,$date_max){
		return $this->getEntityManager()->createQuery("select e.id_annonce, e.titre, e.prix, e.superficie, e.etage, e.pieces, e.lien, e.nom_annonceur,
									e.tel_annonceur, e.email_annonceur, e.quartier, e.commune, e.wilaya, e.description,
									e.type, e.type_bien, e.date
									from Administration\Model\Entity\Annonce as e where e.lien LIKE '%{$source}%' and e.date <='$date_min' and e.date>='$date_max'")->execute();
		
		
	}
	
	public function getAnnoncesBySourceDate($source,$date){
		return $this->getEntityManager()->createQuery("select e.id_annonce, e.titre, e.prix, e.superficie, e.etage, e.pieces, e.lien, e.nom_annonceur,
									e.tel_annonceur, e.email_annonceur, e.quartier, e.commune, e.wilaya, e.description,
									e.type, e.type_bien, e.date
									from Administration\Model\Entity\Annonce as e where e.lien LIKE '%{$source}%' and e.date >= '$date'")->execute();
		
		
	}

	public function getAnnoncesBy($attribute, $value){
		return $this->getEntityManager()->createQuery("select e.id_annonce, e.titre, e.prix, e.superficie, e.etage, e.pieces, e.lien, e.nom_annonceur,
									e.tel_annonceur, e.email_annonceur, e.quartier, e.wilaya, e.description,
									e.type, e.type_bien, e.date
									from Administration\Model\Entity\Annonce as e
									where e.$attribute='$value'")->execute();

	}


	public function getNbAnnonces(){
		return $this->getEntityManager()->createQuery("select COUNT(e.id_annonce) from Administration\Model\Entity\Annonce as e")->execute();
	}
	public function getNbAnnoncesBy($attribute, $value){
		return $this->getEntityManager()->createQuery("select COUNT(e.id_annonce) from Administration\Model\Entity\Annonce as e
								where e.$attribute=$value")->execute();
	}

	public function getNbAnnoncesBySource($value){
		return $this->getEntityManager()->createQuery("select COUNT(e.id_annonce) from Administration\Model\Entity\Annonce as e
								where e.lien LIKE '%{$value}%'")->execute();
	}

	    /*
	    * Mettre à jour le champ prix dans l'annonce
	    */
        public function updatePrice($annonce){
                return $this->getEntityManager()->createQuery("update Administration\Model\Entity\Annonce as e set e.prix='".$annonce['prix']."'               
                                where e.id_annonce='".$annonce['id_annonce']."'"
									)->execute();
	}
	    /*
	     * Mettre à jour le type de l'annonce
	     */
    	public function updateType($annonce){
                return $this->getEntityManager()->createQuery("update Administration\Model\Entity\Annonce as e set e.type='".$annonce['type']."'               
                                where e.id_annonce='".$annonce['id_annonce']."'"
									)->execute();
        }
	    /*
	     * Mettre à jour le type du bien de l'annonce
	     */
    	public function updateTypeBien($annonce){
                return $this->getEntityManager()->createQuery("update Administration\Model\Entity\Annonce as e set e.type_bien='".$annonce['type_bien']."'               
                                where e.id_annonce='".$annonce['id_annonce']."'"
									)->execute();
        }
		/*
		 * Mettre à jour la superficie
		 */
		public function updateSuperficie($annonce){
                return $this->getEntityManager()->createQuery("update Administration\Model\Entity\Annonce as e set e.superficie='".$annonce['superficie']."'               
                                where e.id_annonce='".$annonce['id_annonce']."'"
									)->execute();	
		}
		/*
		 * Mettre à jour la superficie
		 */
		public function updatePieces($annonce){
                return $this->getEntityManager()->createQuery("update Administration\Model\Entity\Annonce as e set e.pieces='".$annonce['pieces']."'               
                                where e.id_annonce='".$annonce['id_annonce']."'"
									)->execute();	
		}
		/*
		 * Mettre à jour la wilaya
		 */
		public function updateWilaya($annonce){
                return $this->getEntityManager()->createQuery("update Administration\Model\Entity\Annonce as e set e.wilaya='".$annonce['wilaya']."'               
                                where e.id_annonce='".$annonce['id_annonce']."'"
									)->execute();	
		}
		/*
		 * Mettre à jour la commune
		 */
		public function updateCommune($annonce){
                return $this->getEntityManager()->createQuery("update Administration\Model\Entity\Annonce as e set e.commune='".$annonce['commune']."'               
                                where e.id_annonce='".$annonce['id_annonce']."'"
									)->execute();	
		}
		/*
		 * Mettre à jour le quartier
		 */
		public function updatePlace($annonce){
                return $this->getEntityManager()->createQuery("update Administration\Model\Entity\Annonce as e set e.quartier='".$annonce['quartier']."'               
                                where e.id_annonce='".$annonce['id_annonce']."'"
									)->execute();	
		}
		/*
		 * Mettre à jour l'email de l'annonceur
		 */
		public function updateEmail($annonce){
                return $this->getEntityManager()->createQuery("update Administration\Model\Entity\Annonce as e set e.email_annonceur='".$annonce['email_annonceur']."'               
                                where e.id_annonce='".$annonce['id_annonce']."'"
									)->execute();	
		}
		/*
		 * Mettre à jour le numero de telephone de l'annonceur
		 */
		public function updateTelephone($annonce){
                return $this->getEntityManager()->createQuery("update Administration\Model\Entity\Annonce as e set e.tel_annonceur='".$annonce['tel_annonceur']."'               
                                where e.id_annonce='".$annonce['id_annonce']."'"
									)->execute();	
		}
	    /*
	     * Récupérer un tableau de montant d'annonces qui sont dans la meme wilaya, qui est de meme type et qui a le meme nombre de pieces
	     */
	public function getAveragePrice($wilaya,$commune, $nbpiece, $type){
        	return $this->getEntityManager()->createQuery("select e.prix
									from Administration\Model\Entity\Annonce as e
									where e.wilaya='$wilaya' and e.commune='$commune' and e.pieces='$nbpiece' and e.type_bien='$type'")->execute();
	} 

	/*
	 * Récupérer les sources disponibles dans la base de données
	 */
	
	public function getSources(){
        	return $this->getEntityManager()->createQuery("select s.id_spider,s.domaine from Administration\Model\Entity\Spider as s")->execute();
	}
	
	/*
	 * Récuperer la liste des wilayas à partir de la base de données
	 */
	public function getWilayaFromDb(){
		return $this->getEntityManager()->createQuery("select w.code,w.nom,w.nom_arabe,w.id from Administration\Model\Entity\Wilaya as w")->execute();
	}
	/*
	 * Récuperer la liste des communes d'une wilaya à partir de la base de données
	 */
	public function getCommuneFromDb($wilaya){
		$code = $this->getEntityManager()->createQuery("select w.code from Administration\Model\Entity\Wilaya as w where w.nom='$wilaya'")->execute()[0]['code'];
		return $this->getEntityManager()->createQuery("select c.nom,c.nom_arabe,c.wilaya_id,c.id from Administration\Model\Entity\Commune as c where c.wilaya_id='".$code."'")->execute();
	}
	
	/*
	 * Récuperer la liste de toutes les communes à partir de la base de données
	 */
	public function getAllCommuneFromDb(){
		return $this->getEntityManager()->createQuery("select c.nom,c.nom_arabe,c.wilaya_id from Administration\Model\Entity\Commune as c")->execute();
	}
    /*
	 * Récuperer la wilya d'une commune à partir de la base de données
	 */
	public function getWilayaOf($commune){
		$code = $this->getEntityManager()->createQuery("select c.wilaya_id from Administration\Model\Entity\Commune as c where LOWER(c.nom)='$commune'")->execute()[0]['wilaya_id'];
		return $this->getEntityManager()->createQuery("select w.nom from Administration\Model\Entity\Wilaya as w where w.code='".$code."'")->execute();
	}
	/*
	 * Récupere l'identifiant d'une commune
	 */
	public function getIdCommune($commune){
		$commune = strtolower($commune);
		return $this->getEntityManager()->createQuery("select c.id from Administration\Model\Entity\Commune as c where LOWER(c.nom)='$commune'")->execute();
	}
	/*
	 * Récupere la superficie d'une annonce
	 */
	public function getSup($annonce){
		return $this->getEntityManager()->createQuery("select e.superficie from Administration\Model\Entity\Annonce as e where e.id_annonce='".$annonce['id_annonce']."'")->execute();
	}
	/*
	 * Récupere toutes les adresses(rue,village,batiment ...etc), données importé depuis osm data
	 */
	public function getAllPlaces(){
		return $this->getEntityManager()->createQuery("select p.id_place,p.design from Administration\Model\Entity\Place as p")->execute();
	}
	/*
	 * Récupere toutes les adresses(rue,village,batiment ...etc) d'une commune données, données importé depuis osm data
	 */
	public function getPlacesByCommune($id_commune){
		return $this->getEntityManager()->createQuery("select p.id_place,p.design from Administration\Model\Entity\Place as p where p.id_commune='$id_commune'")->execute();
	}
	
	public function deleteAnnonce($annonce){
		return $this->getEntityManager()->createQuery("delete from Administration\Model\Entity\Annonce as a where a.id_annonce='".$annonce['id_annonce']."'")->execute();
	}
	
	public function emptyPrice(){
		return $this->getEntityManager()->createQuery("delete from Administration\Model\Entity\Moyenne as e")->execute();
		
		
	}

	
}


