LIB BOUNCER :

Comment fonctionne le getRemediationForIp


**DIRECT MODE**

- IP UNCACHED

  - inconnue de LAPI (bypass): 

$decisions = $this->apiClient->getFilteredDecisions(['ip' => $value]);  => null
=> $decisions = []; (on force empty array si null)

puis on formatRemediationFromDecision
 array ["bypass", time() + $this->cacheExpirati onForCleanIp;, 0]

=> puis on stocke dans le cache (cacheKey = Ip_172.24.0.1)


(eventuellement, on interroge aussi LAPI pour les decisions du COUNTRY et on met en cache cacheKey = Country_FR)


=> on retourne uniquement la rémédiation la plus haute (bypass, captcha, ban)
(en cas de remediation inconnue (mfa), elle devient une priorité haute : pas utilisé car remplacé en amont par fallbackRemediation)



=> on traite éventuellement cette remediation avec des règles métiers : capRemediationLevel

=> enfin on retourne la remediation 

Question : est ce que le RemediationEngine inclut ces règles métiers ou est ce qu'elle retourne la redmediation 
brute ? : plutôt brute pour moi






  - IP connue de LAPI:

On récupère les décisions avec ce format

$decisions = 


"[{\"duration\":\"3h59m40.425606573s\",\"id\":2,\"origin\":\"cscli\",\"scenario\":\"manual 'captcha' from ''\",\"scope\":\"Ip\",\"type\":\"captcha\",\"value\":\"172.24.0.1\"},{\"duration\":\"3h59m46.42560517s\",\"id\":3,\"origin\":\"cscli\",\"scenario\":\"manual 'ban' from ''\",\"scope\":\"Ip\",\"type\":\"ban\",\"value\":\"172.24.0.1\"}]"



Array
(
[0] => Array
(
[duration] => 3h51m4.196667411s
[id] => 2
[origin] => cscli
[scenario] => manual 'captcha' from ''
[scope] => Ip
[type] => captcha
[value] => 172.24.0.1
)

    [1] => Array
        (
            [duration] => 3h51m10.196666455s
            [id] => 3
            [origin] => cscli
            [scenario] => manual 'ban' from ''
            [scope] => Ip
            [type] => ban
            [value] => 172.24.0.1
        )

)


=> on sauvegarde en cache

Lors de la sauvegarde en cache d'une décision, on classe les décisions par priorité de rémédiations ( en récupérant les 
données déjà cachées).

Si la remédiation (decision[type]) est inconnue, on remplace  par la conf "fallbackRemediation";

Ce qui est caché est de la forme : 

[
$decision['type'],  // ex: ban, captcha
time() + $duration, // expiration timestamp
$decision['id'],
];


**STREAM MODE**


- Seul le cache fait foi

(on warme up avant tout)
Au warmp up, un item de cache cacheConfig est stocké
<?php //cacheConfig

return [PHP_INT_MAX, [
    'warmed_up' => true,
]];

Pour récupérer les décisions: 

pullUpdates :
$this->apiClient->getStreamedDecisions(false, $this->getScopes());



si aucune décision : 

"{\"deleted\":null,\"new\":null}"

{"deleted":null,"new":null}



Si on a des décisions : 

{"deleted":null,"new":[{"duration":"3h7m36.876196878s","id":2,"origin":"cscli","scenario":"manual 'captcha' from ''","scope":"Ip","type":"captcha","value":"172.24.0.1"},{"duration":"3h7m42.876194081s","id":3,"origin":"cscli","scenario":"manual 'ban' from ''","scope":"Ip","type":"ban","value":"172.24.0.1"}]}


En cas de deleted : 

{"deleted":[{"duration":"-12.159171034s","id":2,"origin":"cscli","scenario":"manual 'captcha' from ''","scope":"Ip","type":"captcha","value":"172.24.0.1"}],"new":null}


mix des 2 : 

{"deleted":[{"duration":"-12.159171034s","id":4,"origin":"cscli","scenario":"manual 'captcha' from ''","scope":"Ip",
"type":"captcha","value":"172.24.0.1"}],"new":[{"duration":"3h7m36.876196878s","id":2,"origin":"cscli","scenario":"manual 'captcha' from ''","scope":"Ip","type":"captcha","value":"172.24.0.1"},{"duration":"3h7m42.876194081s","id":3,"origin":"cscli","scenario":"manual 'ban' from ''","scope":"Ip","type":"ban","value":"172.24.0.1"}]}



Pour delete l'item du cache, on se base sur l'id de décision:
   - On récupère le cache actuel
   - on enlève la rémediation associée à la décision_id
   - si il ne reste plus d'autre rémédiation pour cet iem, on le delate carrément
   - si il reste en core des remediation, on les réordonne par prio et on sauvegarde l'item.




CAPI CLIENT :


{"new": [{"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "35.197.62.248", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "204.48.26.148", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "162.213.252.92", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "125.212.225.132", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "145.239.67.10", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "213.251.184.216", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "104.248.1.96", "origin": "CAPI", "duration": "164h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "159.89.192.232", "origin": "CAPI", "duration": "162h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "167.99.118.196", "origin": "CAPI", "duration": "160h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "185.220.102.8", "origin": "CAPI", "duration": "142h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "213.152.161.149", "origin": "CAPI", "duration": "141h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "185.220.100.252", "origin": "CAPI", "duration": "135h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "89.187.163.218", "origin": "CAPI", "duration": "129h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "185.220.101.14", "origin": "CAPI", "duration": "121h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "92.205.56.26", "origin": "CAPI", "duration": "113h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "159.242.234.190", "origin": "CAPI", "duration": "111h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "5.8.10.202", "origin": "CAPI", "duration": "111h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "185.220.100.250", "origin": "CAPI", "duration": "110h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "62.102.148.68", "origin": "CAPI", "duration": "103h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "51.178.86.137", "origin": "CAPI", "duration": "98h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "103.216.223.166", "origin": "CAPI", "duration": "96h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "109.70.100.25", "origin": "CAPI", "duration": "79h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "185.220.102.253", "origin": "CAPI", "duration": "65h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "185.34.33.2", "origin": "CAPI", "duration": "57h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "185.220.101.73", "origin": "CAPI", "duration": "49h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "20.243.8.249", "origin": "CAPI", "duration": "38h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "185.16.38.112", "origin": "CAPI", "duration": "24h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "222.127.10.154", "origin": "CAPI", "duration": "13h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "66.240.236.116", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "192.241.213.160", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "81.17.57.144", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "192.241.220.31", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "192.241.206.10", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "195.191.219.130", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "41.72.105.171", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "167.94.138.61", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "51.89.119.182", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "146.59.243.31", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "91.219.254.101", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "167.94.138.62", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "185.25.35.9", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "185.25.35.14", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "136.243.155.105", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "185.196.220.70", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "136.243.212.110", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "213.186.1.246", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "46.4.72.213", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "144.76.73.122", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "167.94.138.44", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "144.76.67.108", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "194.27.156.245", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "65.108.76.15", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "171.244.134.21", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "167.248.133.45", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "132.148.80.89", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "192.241.219.20", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "167.94.138.47", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "136.243.176.156", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "157.245.71.145", "origin": "CAPI", "duration": "166h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "161.35.177.39", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "185.25.35.11", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "65.108.79.209", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "185.25.35.12", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "173.212.220.70", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "192.241.213.188", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "192.241.206.15", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "167.94.145.59", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "198.199.116.39", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "144.76.69.39", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "81.209.177.145", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "192.241.220.30", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "162.142.125.9", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "192.241.217.61", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "42.236.10.100", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "176.9.50.244", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "192.241.214.124", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "212.110.173.87", "origin": "CAPI", "duration": "165h", "type": "ban"}, {"scenario": "crowdsecurity/http-bad-user-agent", "scope": "ip", "value": "137.226.113.44", "origin": "CAPI", "duration": "165h", "type": "ban"}], "deleted": []}



{"new": [{"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "35.197.62.248", "origin": "CAPI", "duration": "167h", "type": "ban"}, {"scenario": "crowdsecurity/http-backdoors-attempts", "scope": "ip", "value": "204.48.26.148", "origin": "CAPI", "duration": "167h", "type": "ban"}], "deleted": []}
