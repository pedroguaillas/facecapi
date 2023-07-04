/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
package sri;

/**
 *
 * @author ccarreno
 */
public class DevelopedSignature {
    //private static Object XAdESBESSignature;
     
    public static void main(String[] args) throws Exception {
               
   /*String xmlPath = "C:\\inetpub\\wwwroot\\Figasa\\Firma\\src\\sri\\factura_xml_No.130_2017-11-30.xml";
    String pathSignature = "C:\\inetpub\\wwwroot\\Figasa\\Firma\\src\\sri\\figaa.p12";
    String passSignature = "Figasa2018";
    String pathOut = "C:\\inetpub\\wwwroot\\Figasa\\Firma\\src\\sri\\";
    String nameFileOut = "factura_firmada.xml";*/

    String pathSignature = args[0];
    String passSignature = args[1];
    String xmlPath = args[2];
    String pathOut = args[3];
    String nameFileOut = args[4];
     
    System.out.println("Ruta del XML de entrada: " + xmlPath);
    System.out.println("Ruta Certificado: " + pathSignature);
    System.out.println("Clave del Certificado: " + passSignature);
    System.out.println("Ruta de salida del XML: " + pathOut);
    System.out.println("Nombre del archivo salido: " + nameFileOut);
     
   
        XAdESBESSignature.firmar(xmlPath, pathSignature, passSignature, pathOut, nameFileOut);
    
}
 
   
}