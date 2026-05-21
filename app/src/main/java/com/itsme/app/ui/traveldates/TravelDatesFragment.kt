package com.itsme.app.ui.traveldates
import android.os.Bundle
import android.view.*
import androidx.appcompat.app.AlertDialog
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.itsme.app.data.entities.TravelDate
import com.itsme.app.databinding.DialogTravelDateFormBinding
import com.itsme.app.databinding.FragmentTravelDatesBinding
import com.itsme.app.viewmodel.TravelDateViewModel

class TravelDatesFragment : Fragment() {
    private var _b: FragmentTravelDatesBinding? = null
    private val b get() = _b!!
    private val vm: TravelDateViewModel by viewModels()

    override fun onCreateView(i: LayoutInflater, c: ViewGroup?, s: Bundle?) =
        FragmentTravelDatesBinding.inflate(i, c, false).also { _b = it }.root

    override fun onViewCreated(v: View, s: Bundle?) {
        val adapter = TravelDateAdapter(
            onActivate = { td -> AlertDialog.Builder(requireContext()).setTitle("Activar viaje")
                .setMessage("¿Activar '${td.name}'?")
                .setPositiveButton("Activar") { _,_ -> vm.activate(td.id) }
                .setNegativeButton("Cancelar",null).show() },
            onComplete = { td -> AlertDialog.Builder(requireContext()).setTitle("Finalizar viaje")
                .setMessage("¿Finalizar '${td.name}'? Los datos pasarán al historial.")
                .setPositiveButton("Finalizar") { _,_ -> vm.complete(td.id) }
                .setNegativeButton("Cancelar",null).show() },
            onDelete = { td -> AlertDialog.Builder(requireContext()).setTitle("Eliminar")
                .setMessage("¿Eliminar '${td.name}'?")
                .setPositiveButton("Eliminar") { _,_ -> vm.delete(td) }
                .setNegativeButton("Cancelar",null).show() }
        )
        b.rvTravelDates.layoutManager = LinearLayoutManager(requireContext())
        b.rvTravelDates.adapter = adapter
        vm.all.observe(viewLifecycleOwner) { adapter.submitList(it) }
        b.fabAddTravelDate.setOnClickListener { showAddDialog() }
    }

    private fun showAddDialog() {
        val vb = DialogTravelDateFormBinding.inflate(layoutInflater)
        AlertDialog.Builder(requireContext()).setTitle("Nuevo Viaje").setView(vb.root)
            .setPositiveButton("Guardar") { _, _ ->
                val name = vb.etTravelName.text.toString().trim()
                if (name.isNotEmpty()) vm.insert(TravelDate(name=name,
                    startDate=vb.etStartDate.text.toString().trim(),
                    endDate=vb.etEndDate.text.toString().trim()))
            }.setNegativeButton("Cancelar",null).show()
    }
    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
