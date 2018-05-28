//senzor 1
#define ECHOPIN1 2       
#define TRIGPIN1 3
//senzor 2
#define ECHOPIN2 4
#define TRIGPIN2 5
void setup()
{
  //seting serial for usual seting
    Serial.begin(9600);
    //setup senzor1
    pinMode(ECHOPIN1, INPUT);
    pinMode(TRIGPIN1, OUTPUT);
  //setup senzor 2
    pinMode(ECHOPIN2, INPUT);
    pinMode(TRIGPIN2, OUTPUT);
    while(!Serial);
    
}
void loop(){}

void serialEvent(){
  byte ca='q';
  ca=Serial.read();
  if(ca=='A'){
    //reading data from sensor 
    unsigned int fst = readHC_SR04 (TRIGPIN1,ECHOPIN1);
    unsigned int scnd = readHC_SR04 (TRIGPIN2,ECHOPIN2);
    //write of ints in binary byte by byte 
    bytePrint(fst,scnd);
  }
}


  
//reading algorithm
unsigned int readHC_SR04(uint8_t trigr, uint8_t echo){
  digitalWrite(trigr, LOW);
  delayMicroseconds(2);
  digitalWrite(trigr, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigr, LOW);
  return pulseIn(echo, HIGH)*0.017315f;
 } 

void bytePrint(unsigned int &in1,unsigned int &in2){
  ///type to aid conversion
  typedef union {
  unsigned int uint;
  byte bin[2];
  } uni;
  
  ///check of  maximum HC-SR04 have maximum of 4 meters so there is 5 for good mesure
  if (in1 >500) in1 =500;
  if (in2 >500) in2 = 500;
  uni out;
  out.uint=0;
  out.uint=in1;

  ///===============1st senzor print
  Serial.write(out.bin[0]);
  Serial.write(out.bin[1]);
  out.uint=0;
  out.uint=in2;
  
 ///===============2nd senzor print
  Serial.write(out.bin[0]);
  Serial.write(out.bin[1]);
 }
