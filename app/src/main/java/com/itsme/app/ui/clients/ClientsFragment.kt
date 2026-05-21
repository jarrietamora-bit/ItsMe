package com.itsme.app.ui.clients
import android.content.Intent
import android.os.Bundle
import android.view.*
import androidx.appcompat.app.AlertDialog
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import androidx.recyclerview.widget.LinearLayoutManager
import com.itsme.app.databinding.FragmentClientsBinding
import com.itsme.app.viewmodel.ClientViewModel

class ClientsFragment : Fragment() {
    private var _b: FragmentClientsBinding? = null
    private val b get() = _b!!
    private val vm: ClientViewModel by viewModels()

    override fun onCreateView(i: LayoutInflater, c: ViewGroup?, s: Bundle?) =
        FragmentClientsBinding.inflate(i, c, false).also { _b = it }.root

    override fun onViewCreated(v: View, s: Bundle?) {
        val adapter = ClientAdapter(
            onEdit = { cl -> startActivity(Intent(requireContext(), ClientFormActivity::class.java).apply {
                putExtra("id", cl.id); putExtra("name", cl.fullName); putExtra("email", cl.email); putExtra("phone", cl.phone) }) },
            onDelete = { cl -> AlertDialog.Builder(requireContext()).setTitle("Eliminar")
                .setMessage("¿Eliminar ${cl.fullName}?").setPositiveButton("Eliminar") { _,_ -> vm.delete(cl) }
                .setNegativeButton("Cancelar", null).show() }
        )
        b.rvClients.layoutManager = LinearLayoutManager(requireContext())
        b.rvClients.adapter = adapter
        vm.all.observe(viewLifecycleOwner) { adapter.submitList(it) }
        b.fabAddClient.setOnClickListener { startActivity(Intent(requireContext(), ClientFormActivity::class.java)) }
    }
    override fun onDestroyView() { super.onDestroyView(); _b = null }
}
