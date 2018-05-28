# AUTOR: JOSEF HAVRANEK AND TOMAS JURICEK		
import serial
from time import sleep
import helper

ser = None

# make serial port or NONE
def vyrobser(portname): 
    for i in range(10):
        try:
            ser = serial.Serial(portname, 9600)
            for j in range(10):
                ser.write(b'A')
                sleep(0.3)
                if ser.in_waiting > 0:
                    print("ARDUINO CONECTED\n")
                    ser.reset_input_buffer()
                    return ser
            print("ARDUINO is not  CONECTED\n")
            return None
        except serial.SerialException as f:
            pass
    print("ARDUINO is not  CONECTED\n")
    return None

# read data from serial return as array of states
#stavy are previous states 
def getstavy(port, stavy):
    if port == None:
        raise serial.SerialException("no port")
    def preved(ktery, data):
        if data == 0 or data >= 500: 
            return 1
        if stavy[ktery]["limit_min"] <= data and stavy[ktery]["limit_max"] >= data:
            return 2
        return 3
    out = []
    port.write(b'A')		
    ingress = ser.read(4)
    prvy = (ingress[1] << 8) + ingress[0]
    druhy = (ingress[3] << 8) + ingress[2]
    out.append(preved(0, prvy))
    out.append(preved(1, druhy))
    print ("SENSOR 1 distance : ", prvy, "SENSOR 2 distance : ", druhy)  # print distance 
    return out

# just for decoding states to string for chcek in terminal 
def senzor_decode(data):			
    if data == 3:
        return "FREE"
    elif data == 2:
        return "OCUPIED"
    return "ERROR"

# MAIN LOOP
try:
    ser = None
    indata = []
    print("DOWNLOADING STATES FROM SERVER...")
    limity = test.vratLimity()
    stavy = test.vratStavy()
    print("\nDOWNLOAD DONE . \n\n INITIAL STATE OF GARAGE  1 is ", senzor_decode(stavy[0]))
    print("Limit (min):", limity[0]["limit_min"], " Limit (max):", limity[0]["limit_max"], "\n")
    print("INITIAL STATE OF GARAGE  2 is", senzor_decode(stavy[1]))
    print("Limit (min):", limity[1]["limit_min"], " Limit (max):", limity[1]["limit_max"], "\n")
    poc = 0

    while True:
        if ser == None:
            ser = vyrobser("/dev/ttyUSB0") 
        try:	
            indata = getstavy(ser, limity)
        except serial.SerialException as f:
            indata = [1,1]
            if  ser!=None:
                print("ARDUINO UNEXPECDELY DISCONECTED")
                ser = None

        if indata[0] != stavy[0]:
            test.updateStav(indata[0], 1)
            stavy[0] = indata[0]
            print("CHANGE - senzor 1 je: ", senzor_decode(indata[0]))
        if indata[1] != stavy[1]:
            test.updateStav(indata[1], 2)
            stavy[1] = indata[1]
            print("CHANGE - senzor 2 je",senzor_decode(indata[1]))

        indata = []
        if poc == 60:
            limity = test.vratLimity()
            poc = 0
            print("\n UPDATING LIMITS...CURENT LIMIT IS :")
            print("GARAGE 1: Limit (min):", limity[0]["limit_min"], " Limit (max):", limity[0]["limit_max"])
            print("GARAGE  2: Limit (min):", limity[1]["limit_min"], " Limit (max):", limity[1]["limit_max"], "\n")
        poc += 1
        sleep(1)
except KeyboardInterrupt:
    print("TERMINATED BY USER...\n\n\n")
